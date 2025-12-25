<?php

use craft\config\GeneralConfig;

return GeneralConfig::create()
    ->devMode(true)
    ->allowAdminChanges(true)
    ->enableTemplateCaching(false)
    ->enableGraphQlCaching(false)
    ->defaultSearchTermOptions([
        'subLeft' => true,
        'subRight' => true,
    ]);
