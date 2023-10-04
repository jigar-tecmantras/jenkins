<?php

namespace App\Traits;

use App\Models\Language;
use Illuminate\Support\Facades\Schema;
use Config;
trait LanguageTrait
{
    public static function getSystemLanguage()
    {
        if (Schema::hasTable('languages')) {
            # Add dynamic user roles in config/constants file
            $language = Language::select('id', 'short_code')->get();

            if (!empty($language)) {
                $languageArray = [];
                foreach ($language as $langRow) {
                    $languageArray[$langRow->short_code] = $langRow->id;
                }
                Config::set('constants.language', $languageArray);
            }
        }
    }
}
