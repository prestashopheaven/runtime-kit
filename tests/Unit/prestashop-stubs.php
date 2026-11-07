<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    prestahaven.com
 * @copyright Since 2026 George van Engers
 * @license   https://opensource.org/licenses/MIT MIT License
 */

/*
 * The few PrestaShop classes UpdaterNotice::render() and ::helpLink() touch, as minimal stand-ins for the unit tests
 * (no PrestaShop). Each one is only defined when the real class is not loaded. Tests set the static fields.
 */

if (!class_exists('Module', false)) {
    class Module
    {
        /** @var list<string> */
        public static array $installed = [];
        /** @var string */
        public $name = '';
        /** @var string */
        public $displayName = '';
        /** @var string */
        public $version = '';
        /** @var string */
        public $module_key = '';

        public static function isInstalled(string $moduleName): bool
        {
            return in_array($moduleName, self::$installed, true);
        }

        /**
         * A translator that marks the text with its domain and locale, so tests can see what was asked.
         */
        public function getTranslator(): object
        {
            return new class {
                /**
                 * @param array<string, mixed> $parameters
                 */
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return "$id [$domain/$locale]";
                }
            };
        }
    }
}

if (!class_exists('Employee', false)) {
    class Employee
    {
        /** @var int */
        public $id_profile = 1;
        public bool $loggedBack = true;

        public function isLoggedBack(): bool
        {
            return $this->loggedBack;
        }
    }
}

if (!class_exists('Language', false)) {
    class Language
    {
        /** @var string */
        public $iso_code = 'en';
    }
}

if (!class_exists('Controller', false)) {
    class Controller
    {
        /** @var string|null */
        public $controller_name;
    }
}

if (!class_exists('Link', false)) {
    class Link
    {
        /**
         * @param array<string, string> $params
         */
        public function getAdminLink(string $controller, bool $withToken = true, array $params = []): string
        {
            return 'https://shop.test/admin/' . $controller . ([] === $params ? '' : '?' . http_build_query($params));
        }
    }
}

if (!class_exists('Context', false)) {
    class Context
    {
        public static ?Context $instance = null;
        public static bool $fail = false;
        /** @var Employee|null */
        public $employee;
        /** @var Language|null */
        public $language;
        /** @var Controller|null */
        public $controller;
        /** @var Link|null */
        public $link;

        public static function getContext(): ?Context
        {
            if (self::$fail) {
                throw new RuntimeException('PrestaShop is not booted');
            }

            return self::$instance;
        }
    }
}

if (!class_exists('Tools', false)) {
    class Tools
    {
        /** @var array<string, string> */
        public static array $values = [];

        /** @var list<string> the URLs redirectAdmin() was called with */
        public static array $redirects = [];

        public static function getValue(string $key, mixed $default = false): mixed
        {
            return self::$values[$key] ?? $default;
        }

        public static function redirectAdmin(string $url): void
        {
            self::$redirects[] = $url;
        }
    }
}

if (!class_exists('Access', false)) {
    class Access
    {
        public static bool $granted = true;

        public static function isGranted(string $role, int $idProfile): bool
        {
            return self::$granted;
        }
    }
}
