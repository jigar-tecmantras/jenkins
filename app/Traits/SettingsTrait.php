<?php

namespace App\Traits;

use DB;
use Illuminate\Support\Facades\Schema;
use Config;

trait SettingsTrait
{
    public static function getSettingsSlug()
    {
        if(Schema::hasTable('settings')){
            # Add dynamic Settings in config/constants file
            $settings = DB::table('settings')->select('id', 'key_name','key_value')->get();

            if(count($settings) > 0) {

                $settingArray = [];

                foreach ($settings as $settingsRow) {
                    $settingArray[$settingsRow->key_name] = $settingsRow->key_value;
                }

                $s3 = [
                    'driver' => 's3',
                    'key' => $settingArray['AWS_ACCESS_KEY_ID'],
                    'secret' => $settingArray['AWS_SECRET_ACCESS_KEY'],
                    'region' => $settingArray['AWS_DEFAULT_REGION'],
                    'bucket' => $settingArray['AWS_BUCKET'],
                    'url' => $settingArray['AWS_URL'],
                    'endpoint' => $settingArray['AWS_ENDPOINT'],
                ];

                $ses = [
                    'key' => $settingArray['AWS_ACCESS_KEY_ID'],
                    'secret' => $settingArray['AWS_SECRET_ACCESS_KEY'],
                    'region' => $settingArray['AWS_DEFAULT_REGION'],
                ];

                 Config::set('constants.settings', $settingArray);
                 Config::set('filesystem.s3',$s3);
                 Config::set('services.ses',$ses);
            }
        }
    }
}
