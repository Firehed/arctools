Arcanist Tools
==============

This library is designed to make it really easy to plug Arcanist and Libphutil
into an existing Composer-based project.

Usage
-----

In your project's root directory, edit the `.arcconfig` file (or create one if
it does not already exist) and add the following values:

    {
        "unit.engine": "Firehed\\Arctools\\PHPUnitTestEngine",
        "load": [
            "vendor/firehed/arctools"
        ]
    }

Additional Configuration
------------------------
* `"phpunit.config": "relative/path/to/phpunit.xml"`
* `"phpunit.source_directory": "path/to/source"`,
* `"phpunit.test_directory": "path/to/tests"`,
* Test suffix (future)

