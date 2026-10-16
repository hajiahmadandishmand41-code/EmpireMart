import fs from 'node:fs';
import path from 'node:path';

const outputRoot = path.resolve(process.argv[2] ?? '.vercel/output/functions');
const builderRoot = path.resolve('.vercel/builders/node_modules/vercel-php');

function fail(message) {
  console.error(`[vercel-php-preview-patch] ERROR: ${message}`);
  process.exit(1);
}

function walk(directory) {
  const files = [];
  if (!fs.existsSync(directory) || !fs.statSync(directory).isDirectory()) return files;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...walk(fullPath));
    else files.push(fullPath);
  }
  return files;
}

if (!fs.existsSync(outputRoot) || !fs.statSync(outputRoot).isDirectory()) {
  fail(`Function output directory not found: ${outputRoot}`);
}

const allFiles = walk(outputRoot);
const configPaths = allFiles.filter((file) => path.basename(file) === '.vc-config.json');
const targets = [];

for (const configPath of configPaths) {
  let config;
  try {
    config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
  } catch (error) {
    fail(`Cannot parse ${configPath}: ${error.message}`);
  }

  if (config.handler === 'launcher.launcher' || config.awsLambdaHandler === 'launcher.launcher') {
    targets.push({ configPath, config });
  }
}

if (targets.length !== 1) {
  fail(`Expected exactly one PHP function using handler "launcher.launcher"; found ${targets.length}. Refusing to deploy an unpatched or ambiguous output.`);
}

const { configPath, config } = targets[0];
const functionRoot = path.dirname(configPath);
const launchers = walk(builderRoot);
const builtinPath = launchers.find((file) => /[/\\]dist[/\\]launchers[/\\]builtin\.js$/.test(file));
const helpersPath = launchers.find((file) => /[/\\]dist[/\\]launchers[/\\]helpers\.js$/.test(file));

if (!builtinPath || !helpersPath) {
  fail(`Cannot find vercel-php launcher sources under ${builderRoot}. The build output was not patched or deployed.`);
}

const originalPath = path.join(functionRoot, 'launcher-orig.js');
const launcherPath = path.join(functionRoot, 'launcher.js');
const helperPath = path.join(functionRoot, 'helpers.js');

if (fs.existsSync(originalPath)) {
  fail(`Refusing to overwrite existing launcher backup: ${originalPath}`);
}

// In this Vercel builder output the launcher's source can be omitted from the
// materialized .func directory. Restore it from the exact builder version used
// for this build rather than inventing or downloading an alternate runtime.
if (fs.existsSync(launcherPath)) {
  fs.renameSync(launcherPath, originalPath);
} else {
  fs.copyFileSync(builtinPath, originalPath);
}

if (!fs.existsSync(helperPath)) {
  fs.copyFileSync(helpersPath, helperPath);
}

// Bridge Vercel's Node bootstrap to the original AWS-style PHP runtime handler.
// Set LAMBDA_TASK_ROOT before importing the runtime helper and normalize bodies
// exactly as expected by the upstream runtime.
fs.writeFileSync(launcherPath, `process.env.LAMBDA_TASK_ROOT = process.env.LAMBDA_TASK_ROOT || "/var/task";
const original = require("./launcher-orig.js");

exports.launcher = async (event, context) => {
  const normalized = { ...event };

  if (Array.isArray(normalized.body)) {
    normalized.body = Buffer.from(normalized.body);
  } else if (typeof normalized.body === "string") {
    normalized.body = Buffer.from(
      normalized.body,
      normalized.isBase64Encoded ? "base64" : "utf8"
    );
  } else {
    normalized.body = undefined;
  }

  const headers = normalized.headers || {};
  normalized.host = normalized.host || headers["x-forwarded-host"] || headers.host;

  return original.launcher(normalized, context);
};
`, 'utf8');

config.handler = 'launcher.js';
config.launcherType = 'Nodejs';
config.awsLambdaHandler = 'launcher.launcher';
fs.writeFileSync(configPath, JSON.stringify(config, null, 2) + '\n', 'utf8');

const verified = JSON.parse(fs.readFileSync(configPath, 'utf8'));
if (
  verified.handler !== 'launcher.js' ||
  verified.launcherType !== 'Nodejs' ||
  verified.awsLambdaHandler !== 'launcher.launcher' ||
  !fs.existsSync(launcherPath) ||
  !fs.existsSync(originalPath) ||
  !fs.existsSync(helperPath)
) {
  fail('Post-patch verification failed. Refusing to deploy.');
}

console.log('[vercel-php-preview-patch] Patched one PHP function output.');
console.log(`[vercel-php-preview-patch] Config: ${path.relative(process.cwd(), configPath)}`);
console.log('[vercel-php-preview-patch] Handler: launcher.js (Nodejs / launcher.launcher)');
console.log('[vercel-php-preview-patch] Original launcher and helpers are present.');
