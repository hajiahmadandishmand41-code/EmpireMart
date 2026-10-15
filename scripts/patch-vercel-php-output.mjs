import fs from 'node:fs';
import path from 'node:path';

const outputRoot = path.resolve(process.argv[2] ?? '.vercel/output/functions');

function fail(message) {
  console.error(`[vercel-php-preview-patch] ERROR: ${message}`);
  process.exit(1);
}

if (!fs.existsSync(outputRoot) || !fs.statSync(outputRoot).isDirectory()) {
  fail(`Function output directory not found: ${outputRoot}`);
}

function walk(directory) {
  const files = [];
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...walk(fullPath));
    else files.push(fullPath);
  }
  return files;
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
const originalPath = path.join(functionRoot, 'launcher.js');
const backupPath = path.join(functionRoot, 'launcher-orig.js');

if (!fs.existsSync(originalPath) || !fs.statSync(originalPath).isFile()) {
  fail(`Expected runtime launcher beside ${configPath}: ${originalPath}`);
}
if (fs.existsSync(backupPath)) {
  fail(`Refusing to overwrite existing launcher backup: ${backupPath}`);
}

// Keep the runtime's original CommonJS export and bridge the Vercel event format.
fs.renameSync(originalPath, backupPath);
fs.writeFileSync(originalPath, `process.env.LAMBDA_TASK_ROOT = process.env.LAMBDA_TASK_ROOT || "/var/task";
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

// Fail closed if a second PHP entrypoint or an incompatible output format was missed.
const verified = JSON.parse(fs.readFileSync(configPath, 'utf8'));
if (
  verified.handler !== 'launcher.js' ||
  verified.launcherType !== 'Nodejs' ||
  verified.awsLambdaHandler !== 'launcher.launcher' ||
  !fs.existsSync(backupPath)
) {
  fail('Post-patch verification failed.');
}

console.log('[vercel-php-preview-patch] Patched one PHP function output.');
console.log(`[vercel-php-preview-patch] Config: ${path.relative(process.cwd(), configPath)}`);
console.log('[vercel-php-preview-patch] Handler: launcher.js (Nodejs / launcher.launcher)');
