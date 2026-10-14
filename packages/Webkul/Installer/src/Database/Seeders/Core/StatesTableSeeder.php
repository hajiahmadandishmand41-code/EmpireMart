<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Helpers\AfghanistanProvinces;

class StatesTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('country_states')->delete();

        $states = json_decode(file_get_contents(__DIR__.'/../../../Data/states.json'), true);

        DB::table('country_states')->insert($states);

        $this->seedAfghanProvinces();
    }

    /**
     * The states file that ships with the platform has no provinces for
     * Afghanistan, so they are added here and translated for every locale the
     * installation runs in. A store that was already running gets the same rows
     * from the migrations instead.
     */
    protected function seedAfghanProvinces(): void
    {
        if (! DB::table('countries')->where('code', 'AF')->exists()) {
            return;
        }

        $countryId = DB::table('countries')->where('code', 'AF')->value('id');

        $locales = DB::table('locales')->pluck('code')->all();

        foreach (AfghanistanProvinces::codes() as $code) {
            $stateId = DB::table('country_states')->insertGetId([
                'country_id' => $countryId,
                'country_code' => 'AF',
                'code' => $code,
                'default_name' => AfghanistanProvinces::name($code, 'en'),
            ]);

            foreach ($locales as $locale) {
                DB::table('country_state_translations')->insert([
                    'country_state_id' => $stateId,
                    'locale' => $locale,
                    'default_name' => AfghanistanProvinces::name($code, $locale),
                ]);
            }
        }
    }
}
