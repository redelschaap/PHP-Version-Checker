# PHP-Version-Checker

Checks PHP project files against the internal functions and classes of PHP to determine the minimum required PHP version



### How to use
 1. Place all the files and folder(s) from this project in the root of your project and include the `php_version_check.php` file in your index.php: `<?php include('php_version_check.php'); ?>`.
 2. Request your index.php with `?action=check_version` to check your PHP files
 3. When you need to update the functions (when a new PHP version is released for example), run your index.php with `?action=crawl_functions` to crawl all the functions from the PHP.net website



### Versions
The latest version is `V1.0` and can be downloaded from https://github.com/Ronald01990/PHP-Version-Checker/archive/1.0.zip
