<?php

class App {
    /**
     * @var CSmarty
     */
    private static $templater;

    /**
     * @var Database
     */
    private static $db;

    /**
     * @var AppOptions
     */
    private static $options;

    /**
     * Prepares and returns database connection.
     * 
     * @return Database
     */
    public static function db()
    {
        if (!self::$db)
        {
            self::$db = \DatabaseManager::GetConnection();
        }

        return self::$db;
    }

    /**
     * Prepares and returns template compiler.
     * 
     * @return CSmarty
     */
    public static function templater()
    {
        if (!self::$templater)
        {
            require(INCLUDES_PATH . '/smarty/bootstrap.php');

            $templater = new Smarty();
            $templater->setErrorReporting(E_ALL ^ E_NOTICE);
            $templater->setUseSubDirs(false);
            $templater->setCachingType(Smarty::CACHING_LIFETIME_SAVED);
            $templater->setTemplateDir(SB_THEME);
            $templater->setCompileDir(SB_THEME_COMPILE);
            $templater->compile_id = $_SERVER['SERVER_NAME'];

            // This required for Smarty 3.
            $templater->default_resource_type = 'sb__wrapper';
            $templater->registerResource('sb__wrapper', new \SmartyTemplateWrapper('sb://theme'));

            $templater->assign('SITE_ADDRESS',  SB_WP_URL);
            $templater->assign('SBConfig',      ReplaceArrayKeyNames(self::options(), '.', '_'));

            \StreamWrapper::addVirtualHost('theme', SB_THEME);
            \StreamWrapper::addHook('theme', function (&$path, $relativePath)
            {
                $userModTemplate = SB_USER_THEME . $relativePath;
                if (file_exists($userModTemplate))
                {
                    // our modified template file exists.
                    // we use mod instead original.
                    $path = $userModTemplate;
                }
            });

            self::$templater = $templater;
        }

        return self::$templater;
    }

    /**
     * Prepares and returns configuration.
     * 
     * @return AppOptions
     */
    public static function options()
    {
        if (!self::$options)
        {
            $options = [];
            $result = self::db()->Query("SELECT `setting`, `value` FROM `{{prefix}}settings`");

            while ($row = $result->Single())
            {
                $options[$row['setting']] = $row['value'];
            }
            
            self::$options = new \AppOptions($options);
        }

        return self::$options;
    }
}