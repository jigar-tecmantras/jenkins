<?php

namespace App\Traits;

use DB;
use Illuminate\Support\Facades\Schema;
use Config;

trait UserRoleTrait
{
    public static function getUserRoleSlug()
    {
        if(Schema::hasTable('roles')){
            # Add dynamic user roles in config/constants file
            $userRole = DB::table('roles')->select('id', 'guard_name')->get();

            if(!empty($userRole)) {
                $roleArray = [];
                foreach ($userRole as $roleRow) {
                    $roleArray[$roleRow->guard_name] = $roleRow->id;
                }
                 Config::set('constants.role', $roleArray);
            }
        }
    }
}
