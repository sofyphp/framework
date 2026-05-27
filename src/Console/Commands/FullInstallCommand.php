<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class FullInstallCommand extends Command
{
    protected string $signature   = 'full-install {--no-interaction : Skip wizard, use defaults}';
    protected string $description = 'Provision the server: PHP, web server, cron, Supervisor, migrations';

    // ── Entry point ───────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->banner();

        if (!$this->preflightChecks()) {
            return 1;
        }

        $config = $this->option('no-interaction')
            ? $this->defaultConfig()
            : $this->runWizard();

        if ($config === null) {
            $this->warn('Installation cancelled.');
            return 0;
        }

        return $this->runInstall($config);
    }

    // ── Wizard ────────────────────────────────────────────────────────────────

    private function runWizard(): ?array
    {
        $total = 6;

        // Step 1 — Domain
        $this->stepHeader(1, $total, 'Domain & Environment');
        $domain = $this->ask('Domain or server IP (e.g. example.com or 1.2.3.4):');
        if ($domain === '') {
            $domain = 'localhost';
        }

        // Step 2 — PHP version
        $this->stepHeader(2, $total, 'PHP Version');
        $php = (string) $this->select('Which PHP version to install?', [
            '8.3' => 'PHP 8.3  — latest stable (recommended)',
            '8.2' => 'PHP 8.2  — previous stable',
            '8.1' => 'PHP 8.1  — LTS',
        ], '8.3');

        // Step 3 — Web server
        $this->stepHeader(3, $total, 'Web Server');
        $webserver = (string) $this->select('Which web server to install?', [
            'caddy'  => 'Caddy   — automatic HTTPS + auto-renew (recommended)',
            'nginx'  => 'Nginx   — high performance, SSL via Certbot',
            'apache' => 'Apache  — classic, SSL via Certbot',
        ], 'caddy');

        // Step 4 — SSL
        $this->stepHeader(4, $total, 'SSL Certificate');
        [$ssl, $email] = $this->askSslConfig($domain, $webserver);

        // Step 5 — Database
        $this->stepHeader(5, $total, 'Database');
        $db = (string) $this->select('Which database driver?', [
            'mysql'  => 'MySQL / MariaDB  — most common',
            'pgsql'  => 'PostgreSQL       — advanced SQL',
            'sqlite' => 'SQLite           — file-based, no server needed',
        ], 'mysql');

        [$db_name, $db_user, $db_pass] = $this->askDbCredentials($db);

        // Step 6 — Extras
        $this->stepHeader(6, $total, 'Additional Components');
        $cron       = $this->confirm('Setup cron for scheduler (schedule:run every minute)?', true);
        $supervisor = $this->confirm('Install Supervisor and setup queue workers?', true);
        $migrate    = $this->confirm('Run database migrations after install?', true);

        // Summary
        $this->line();
        $this->printSummaryBox($domain, $php, $webserver, $ssl, $email, $db, $db_name, $db_user, $cron, $supervisor, $migrate);

        if (!$this->confirm('Proceed with installation?', true)) {
            return null;
        }

        return compact('domain', 'php', 'webserver', 'ssl', 'email', 'db', 'db_name', 'db_user', 'db_pass', 'cron', 'supervisor', 'migrate');
    }

    /**
     * Спрашивает про SSL в зависимости от веб-сервера и домена.
     * Возвращает [ssl: bool, email: string].
     */
    private function askSslConfig(string $domain, string $webserver): array
    {
        $realDomain = $this->isRealDomain($domain);

        if ($webserver === 'caddy') {
            if ($realDomain) {
                $this->info('Caddy will issue and renew SSL certificates automatically via Let\'s Encrypt.');
                $this->line('  No configuration needed — HTTPS is enabled out of the box.');
            } else {
                $this->warn('Domain is localhost / IP — Caddy will use a self-signed certificate.');
            }
            return [true, ''];
        }

        // Nginx or Apache — needs Certbot
        if (!$realDomain) {
            $this->warn("Domain [$domain] is localhost or an IP address.");
            $this->warn('Let\'s Encrypt requires a real public domain. SSL will be skipped.');
            return [false, ''];
        }

        $this->line("Certbot (Let's Encrypt) will be installed to issue a free SSL certificate.");
        $this->line('Your domain must point to this server\'s IP before proceeding.');
        $this->line();

        $ssl = $this->confirm('Issue SSL certificate via Let\'s Encrypt (Certbot)?', true);

        $email = '';
        if ($ssl) {
            $email = $this->ask('Email for Let\'s Encrypt notifications (renewal warnings):');
            if ($email === '') {
                $this->warn('No email provided — using --register-unsafely-without-email.');
            }
        }

        return [$ssl, $email];
    }

    private function defaultConfig(): array
    {
        return [
            'domain'     => 'localhost',
            'php'        => '8.3',
            'webserver'  => 'caddy',
            'ssl'        => true,
            'email'      => '',
            'db'         => 'mysql',
            'db_name'    => 'sofy',
            'db_user'    => 'sofy',
            'db_pass'    => '',    // empty → auto-generated in configureDatabase()
            'cron'       => true,
            'supervisor' => true,
            'migrate'    => true,
        ];
    }

    // ── Installation ──────────────────────────────────────────────────────────

    private function runInstall(array $cfg): int
    {
        $steps = $this->buildSteps($cfg);
        $total = count($steps);
        $step  = 1;

        $this->line();
        $this->info('Starting installation...');

        foreach ($steps as $label => $fn) {
            $this->line();
            $this->comment("[$step/$total] $label");
            $this->line(str_repeat('─', 50));

            if ($fn() === false) {
                $this->error("Installation failed at: $label");
                return 1;
            }

            $step++;
        }

        $this->printDoneBanner($cfg);
        return 0;
    }

    private function buildSteps(array $cfg): array
    {
        $steps = [
            'Installing system packages'                 => fn() => $this->installPackages($cfg),
            'Installing Composer'                        => fn() => $this->installComposer(),
            'Installing project dependencies'            => fn() => $this->composerInstall(),
            'Setting up .env'                            => fn() => $this->setupEnv($cfg),
            'Configuring database'                       => fn() => $this->configureDatabase($cfg),
            'Setting file permissions'                   => fn() => $this->setPermissions(),
            'Configuring ' . ucfirst($cfg['webserver'])  => fn() => $this->configureWebServer($cfg),
        ];

        if ($cfg['cron']) {
            $steps['Setting up cron'] = fn() => $this->setupCron();
        }

        if ($cfg['supervisor']) {
            $steps['Configuring Supervisor'] = fn() => $this->configureSupervisor();
        }

        if ($cfg['migrate']) {
            $steps['Running migrations'] = fn() => $this->runMigrations();
        }

        return $steps;
    }

    // ── Step: packages ────────────────────────────────────────────────────────

    private function installPackages(array $cfg): bool
    {
        $phpV = $cfg['php'];
        $os   = $cfg['_os'] ?? 'debian';

        $dbPkg = match ($cfg['db']) {
            'pgsql'  => "php$phpV-pgsql",
            'sqlite' => "php$phpV-sqlite3",
            default  => "php$phpV-mysql",
        };

        $phpPkgs = "php$phpV-fpm php$phpV-cli php$phpV-curl php$phpV-xml php$phpV-mbstring php$phpV-zip php$phpV-bcmath php$phpV-intl $dbPkg";

        $wsPkg = match ($cfg['webserver']) {
            'nginx'  => 'nginx',
            'apache' => 'apache2 libapache2-mod-fcgid',
            default  => '', // caddy added separately
        };

        $extra = $cfg['supervisor'] ? ' supervisor' : '';

        $dbServerPkg = match ($cfg['db']) {
            'pgsql'  => 'postgresql postgresql-client',
            'sqlite' => '',
            default  => 'mariadb-server',
        };

        if ($os === 'debian') {
            $cmds = [
                'apt-get update -qq',
                "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends curl gnupg2 ca-certificates unzip cron$extra",
                'DEBIAN_FRONTEND=noninteractive apt-get install -y software-properties-common',
                'LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php 2>/dev/null || true',
                'apt-get update -qq',
                "DEBIAN_FRONTEND=noninteractive apt-get install -y $phpPkgs",
            ];

            if ($dbServerPkg !== '') {
                $cmds[] = "DEBIAN_FRONTEND=noninteractive apt-get install -y $dbServerPkg";
            }

            if ($wsPkg !== '') {
                $cmds[] = "DEBIAN_FRONTEND=noninteractive apt-get install -y $wsPkg";
            }

            if ($cfg['webserver'] === 'caddy') {
                $cmds = array_merge($cmds, [
                    'DEBIAN_FRONTEND=noninteractive apt-get install -y debian-keyring debian-archive-keyring apt-transport-https',
                    "curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg",
                    "curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list",
                    'apt-get update -qq',
                    'DEBIAN_FRONTEND=noninteractive apt-get install -y caddy',
                ]);
            }

            if ($cfg['webserver'] === 'apache') {
                $cmds[] = 'a2enmod rewrite proxy_fcgi setenvif';
                $cmds[] = "a2enconf php$phpV-fpm";
            }
        } else {
            // RHEL / CentOS
            $cmds = [
                'dnf update -y -q',
                "dnf install -y curl unzip cronie" . ($cfg['supervisor'] ? ' supervisor' : ''),
                "dnf install -y php php-fpm php-{$cfg['db']} php-curl php-xml php-mbstring php-zip php-bcmath php-intl",
            ];

            if ($cfg['webserver'] === 'nginx') {
                $cmds[] = 'dnf install -y nginx';
            } elseif ($cfg['webserver'] === 'apache') {
                $cmds[] = 'dnf install -y httpd mod_fcgid';
            } else {
                $cmds = array_merge($cmds, [
                    "rpm --import 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key'",
                    "curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/rpm.repo' | tee /etc/yum.repos.d/caddy-stable.repo",
                    'dnf install -y caddy',
                ]);
            }
        }

        return $this->runAll($cmds);
    }

    // ── Step: Composer ────────────────────────────────────────────────────────

    private function installComposer(): bool
    {
        if ($this->exec('which composer > /dev/null 2>&1') === 0) {
            $this->info('Composer already installed — skipping.');
            return true;
        }

        return $this->runAll([
            "php -r \"copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');\"",
            'php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer',
            'rm /tmp/composer-setup.php',
        ]);
    }

    private function composerInstall(): bool
    {
        $path = base_path();
        return $this->exec("composer install --no-dev --optimize-autoloader --no-interaction --working-dir=$path") === 0;
    }

    // ── Step: .env ────────────────────────────────────────────────────────────

    private function setupEnv(array $cfg): bool
    {
        $env     = base_path('.env');
        $example = base_path('.env.example');

        if (file_exists($env)) {
            $this->info('.env already exists — skipping.');
            return true;
        }

        if (!file_exists($example)) {
            $this->warn('.env.example not found — skipping.');
            return true;
        }

        copy($example, $env);
        $this->replaceInEnv($env, 'APP_ENV',   'production');
        $this->replaceInEnv($env, 'APP_DEBUG', 'false');
        $this->replaceInEnv($env, 'APP_URL',   "https://$cfg[domain]");
        $this->success('.env created from .env.example — DB credentials will be written in the next step.');

        return true;
    }

    // ── Step: permissions ─────────────────────────────────────────────────────

    private function setPermissions(): bool
    {
        foreach (['storage', 'public'] as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                continue;
            }
            $this->exec("chmod -R 775 $path");
            $this->exec("chown -R www-data:www-data $path");
        }
        $this->success('Permissions set.');
        return true;
    }

    // ── Step: web server ──────────────────────────────────────────────────────

    private function configureWebServer(array $cfg): bool
    {
        return match ($cfg['webserver']) {
            'nginx'  => $this->configureNginx($cfg),
            'apache' => $this->configureApache($cfg),
            default  => $this->configureCaddy($cfg),
        };
    }

    private function configureCaddy(array $cfg): bool
    {
        $phpV   = $cfg['php'];
        $public = base_path('public');
        $sock   = "/run/php/php$phpV-fpm.sock";

        $caddyfile = <<<CONF
        # Generated by php sofy full-install

        $cfg[domain] {
            root * $public

            php_fastcgi unix/$sock

            encode zstd gzip

            file_server

            @notFile not file
            rewrite @notFile /index.php

            log {
                output file /var/log/caddy/sofy.log
                format console
            }
        }
        CONF;

        file_put_contents('/etc/caddy/Caddyfile', $caddyfile);
        $this->info('Caddyfile written → /etc/caddy/Caddyfile');

        return $this->runAll([
            "systemctl enable php$phpV-fpm caddy",
            "systemctl restart php$phpV-fpm",
            'systemctl reload-or-restart caddy || systemctl restart caddy',
        ]);
    }

    private function configureNginx(array $cfg): bool
    {
        $phpV    = $cfg['php'];
        $public  = base_path('public');
        $sock    = "/run/php/php$phpV-fpm.sock";
        $domain  = $cfg['domain'];
        $cfgFile = "/etc/nginx/sites-available/sofy";

        $conf = <<<CONF
        # Generated by php sofy full-install

        server {
            listen 80;
            listen [::]:80;
            server_name $domain;
            root $public;
            index index.php;
            charset utf-8;

            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }

            location ~ \.php$ {
                fastcgi_pass unix:$sock;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
                include fastcgi_params;
            }

            location ~ /\.(?!well-known).* {
                deny all;
            }

            access_log /var/log/nginx/sofy_access.log;
            error_log  /var/log/nginx/sofy_error.log;
        }
        CONF;

        file_put_contents($cfgFile, $conf);
        $this->info("Nginx config written → $cfgFile");

        $ok = $this->runAll([
            "ln -sf $cfgFile /etc/nginx/sites-enabled/sofy",
            'rm -f /etc/nginx/sites-enabled/default',
            'nginx -t',
            "systemctl enable php$phpV-fpm nginx",
            "systemctl restart php$phpV-fpm",
            'systemctl reload-or-restart nginx || systemctl restart nginx',
        ]);

        if ($ok && $cfg['ssl']) {
            $ok = $this->issueCertbot($cfg, 'nginx');
        }

        return $ok;
    }

    private function configureApache(array $cfg): bool
    {
        $phpV    = $cfg['php'];
        $public  = base_path('public');
        $sock    = "/run/php/php$phpV-fpm.sock";
        $domain  = $cfg['domain'];
        $cfgFile = '/etc/apache2/sites-available/sofy.conf';

        $conf = <<<CONF
        # Generated by php sofy full-install

        <VirtualHost *:80>
            ServerName $domain
            DocumentRoot $public
            DirectoryIndex index.php

            <Directory $public>
                Options -Indexes +FollowSymLinks
                AllowOverride All
                Require all granted
            </Directory>

            <FilesMatch \.php$>
                SetHandler "proxy:unix:$sock|fcgi://localhost"
            </FilesMatch>

            ErrorLog  \${APACHE_LOG_DIR}/sofy_error.log
            CustomLog \${APACHE_LOG_DIR}/sofy_access.log combined
        </VirtualHost>
        CONF;

        file_put_contents($cfgFile, $conf);
        $this->info("Apache config written → $cfgFile");

        // .htaccess in public/
        $htaccess = <<<'HTA'
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [L]
        </IfModule>
        HTA;

        file_put_contents("$public/.htaccess", $htaccess);
        $this->info('.htaccess written → public/.htaccess');

        $ok = $this->runAll([
            "a2ensite sofy.conf",
            'a2dissite 000-default.conf 2>/dev/null || true',
            "systemctl enable php$phpV-fpm apache2",
            "systemctl restart php$phpV-fpm",
            'apachectl configtest && systemctl reload-or-restart apache2 || systemctl restart apache2',
        ]);

        if ($ok && $cfg['ssl']) {
            $ok = $this->issueCertbot($cfg, 'apache');
        }

        return $ok;
    }

    // ── Step: cron ────────────────────────────────────────────────────────────

    private function setupCron(): bool
    {
        $php      = trim((string) shell_exec('which php')) ?: '/usr/bin/php';
        $sofy     = base_path('sofy');
        $cronFile = '/etc/cron.d/sofy-scheduler';

        file_put_contents($cronFile, "* * * * * www-data $php $sofy schedule:run >> /dev/null 2>&1\n");
        chmod($cronFile, 0644);
        $this->info("Cron entry written → $cronFile");

        $this->exec('systemctl enable cron 2>/dev/null || systemctl enable crond 2>/dev/null || true');
        $this->exec('systemctl restart cron 2>/dev/null || systemctl restart crond 2>/dev/null || true');
        return true;
    }

    // ── Step: Supervisor ──────────────────────────────────────────────────────

    private function configureSupervisor(): bool
    {
        $php   = trim((string) shell_exec('which php')) ?: '/usr/bin/php';
        $sofy  = base_path('sofy');
        $logs  = base_path('storage/logs');
        $file  = '/etc/supervisor/conf.d/sofy-worker.conf';

        $conf = <<<INI
        [program:sofy-worker]
        process_name=%(program_name)s_%(process_num)02d
        command=$php $sofy queue:work --sleep=3
        autostart=true
        autorestart=true
        stopasgroup=true
        killasgroup=true
        user=www-data
        numprocs=2
        redirect_stderr=true
        stdout_logfile=$logs/worker.log
        stopwaitsecs=3600
        INI;

        file_put_contents($file, $conf);
        $this->info("Supervisor config written → $file");

        $this->exec('supervisorctl reread 2>/dev/null || true');
        $this->exec('supervisorctl update 2>/dev/null || true');
        $this->exec('supervisorctl start sofy-worker:* 2>/dev/null || true');
        return true;
    }

    // ── Step: migrate ─────────────────────────────────────────────────────────

    private function runMigrations(): bool
    {
        $php = trim((string) shell_exec('which php')) ?: 'php';
        return $this->exec("$php " . base_path('sofy') . ' migrate') === 0;
    }

    // ── Pre-flight ────────────────────────────────────────────────────────────

    private function preflightChecks(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->error('full-install only supports Linux servers.');
            return false;
        }

        if (function_exists('posix_getuid') && posix_getuid() !== 0) {
            $this->error('Run as root or with sudo:');
            $this->line('  sudo php sofy full-install');
            return false;
        }

        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function runAll(array $cmds): bool
    {
        foreach ($cmds as $cmd) {
            $this->comment("  \$ $cmd");
            if ($this->exec($cmd) !== 0) {
                $this->error("  ✗ Failed: $cmd");
                return false;
            }
        }
        return true;
    }

    private function exec(string $cmd): int
    {
        passthru($cmd, $code);
        return $code;
    }

    private function isRealDomain(string $domain): bool
    {
        if ($domain === 'localhost' || $domain === '') {
            return false;
        }
        // IP address — not a real domain
        if (filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        // Must contain a dot and at least a two-letter TLD
        return (bool) preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z]{2,})+$/i', $domain);
    }

    private function issueCertbot(array $cfg, string $plugin): bool
    {
        $domain = $cfg['domain'];
        $email  = $cfg['email'] ?? '';

        if ($email !== '') {
            $emailFlag = "--email $email --agree-tos --no-eff-email";
        } else {
            $emailFlag = '--register-unsafely-without-email --agree-tos';
        }

        $certbotInstall = $this->runAll([
            'DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-' . $plugin,
        ]);

        if (!$certbotInstall) {
            return false;
        }

        $this->info("Issuing SSL certificate for $domain via Certbot ($plugin plugin)...");

        return $this->exec(
            "certbot --$plugin $emailFlag --non-interactive --redirect -d $domain"
        ) === 0;
    }

    private function replaceInEnv(string $file, string $key, string $value): void
    {
        $content = (string) file_get_contents($file);
        $content = preg_replace("/^$key=.*/m", "$key=$value", $content) ?? $content;
        file_put_contents($file, $content);
    }

    private function printSummaryBox(
        string $domain, string $php, string $webserver,
        bool $ssl, string $email,
        string $db, string $db_name, string $db_user,
        bool $cron, bool $supervisor, bool $migrate,
    ): void {
        $yes = "\033[32myes\033[0m";
        $no  = "\033[31mno\033[0m";

        $sslLabel = $ssl
            ? ($email !== '' ? "$yes (Certbot, $email)" : $yes)
            : $no;

        $dbLabel = match ($db) {
            'pgsql'  => 'PostgreSQL',
            'sqlite' => 'SQLite',
            default  => 'MySQL / MariaDB',
        };

        $this->line();
        $this->line('  ┌─────────────────────────────────────────┐');
        $this->line('  │           Installation Summary          │');
        $this->line('  ├─────────────────────────────────────────┤');
        $this->line("  │  Domain      : \033[1m$domain\033[0m");
        $this->line("  │  PHP         : $php");
        $this->line("  │  Web server  : \033[1m" . ucfirst($webserver) . "\033[0m");
        $this->line("  │  SSL         : $sslLabel");
        $this->line("  │  Database    : $dbLabel");
        if ($db !== 'sqlite') {
            $this->line("  │  DB name     : $db_name");
            $this->line("  │  DB user     : $db_user");
        }
        $this->line("  │  Cron        : " . ($cron       ? $yes : $no));
        $this->line("  │  Supervisor  : " . ($supervisor ? $yes : $no));
        $this->line("  │  Migrations  : " . ($migrate    ? $yes : $no));
        $this->line('  └─────────────────────────────────────────┘');
        $this->line();
    }

    private function printDoneBanner(array $cfg): void
    {
        $this->line();
        $this->line('  ┌─────────────────────────────────────────┐');
        $this->success('  │       Installation complete!           │');
        $this->line('  └─────────────────────────────────────────┘');
        $this->line();
        $this->line("  Site     → https://$cfg[domain]");
        $this->line("  Project  → " . base_path());
        $this->line();
        $this->comment('Useful commands:');
        $this->line("  php sofy queue:work        — manual worker");
        $this->line("  supervisorctl status       — worker status");
        $this->line("  systemctl status $cfg[webserver]     — web server");
        $this->line("  php sofy migrate           — run migrations");
        $this->line();
    }

    // ── Database wizard helpers ───────────────────────────────────────────────

    /**
     * Ask for DB credentials during the wizard.
     * Returns [db_name, db_user, db_pass]. All empty for SQLite.
     */
    private function askDbCredentials(string $driver): array
    {
        if ($driver === 'sqlite') {
            $file = function_exists('base_path')
                ? base_path('database/database.sqlite')
                : 'database/database.sqlite';
            $this->info("SQLite file will be created at: $file");
            return ['', '', ''];
        }

        $name = $this->ask('Database name (default: sofy):');
        $name = $name !== '' ? $name : 'sofy';

        $user = $this->ask('Database user (default: sofy):');
        $user = $user !== '' ? $user : 'sofy';

        $pass = $this->ask('Database password (leave empty to auto-generate):');

        if ($pass === '') {
            $pass = $this->generatePassword();
            $this->info("Generated password: \033[1m$pass\033[0m");
            $this->warn('This password will be written to .env automatically.');
        }

        return [$name, $user, $pass];
    }

    // ── Step: configure database ──────────────────────────────────────────────

    private function configureDatabase(array $cfg): bool
    {
        $driver = $cfg['db'];
        $name   = $cfg['db_name'] ?? 'sofy';
        $user   = $cfg['db_user'] ?? 'sofy';
        $pass   = $cfg['db_pass'] ?? '';

        if ($pass === '') {
            $pass = $this->generatePassword();
            $this->info("Generated DB password: \033[1m$pass\033[0m");
            $this->warn('Password written to .env — save it somewhere safe.');
        }

        // Write all DB_* vars to .env
        $this->writeDbEnv(base_path('.env'), $driver, $name, $user, $pass);

        return match ($driver) {
            'pgsql'  => $this->configurePostgres($name, $user, $pass),
            'sqlite' => $this->configureSqlite(),
            default  => $this->configureMysql($name, $user, $pass),
        };
    }

    private function configureMysql(string $name, string $user, string $pass): bool
    {
        $escapedPass = str_replace("'", "\\'", $pass);
        $sql = implode('; ', [
            "CREATE DATABASE IF NOT EXISTS \`$name\`",
            "CREATE USER IF NOT EXISTS '$user'@'localhost' IDENTIFIED BY '$escapedPass'",
            "GRANT ALL PRIVILEGES ON \`$name\`.* TO '$user'@'localhost'",
            'FLUSH PRIVILEGES',
        ]);

        $ok = $this->runAll([
            'systemctl enable --now mariadb || systemctl enable --now mysql || true',
            "mysql -u root -e \"$sql\"",
        ]);

        if ($ok) {
            $this->success("MySQL database '$name' created, user '$user' granted.");
        }

        return $ok;
    }

    private function configurePostgres(string $name, string $user, string $pass): bool
    {
        $escapedPass = str_replace("'", "\\'", $pass);

        $ok = $this->runAll([
            'systemctl enable --now postgresql',
            // create user (ignore if exists) then set password
            "sudo -u postgres psql -c \"DO \$\$ BEGIN " .
                "CREATE ROLE $user LOGIN PASSWORD '$escapedPass'; " .
                "EXCEPTION WHEN duplicate_object THEN " .
                "ALTER ROLE $user WITH PASSWORD '$escapedPass'; END \$\$;\"",
            // create database (ignore if exists)
            "sudo -u postgres psql -c \"SELECT 1 FROM pg_database WHERE datname='$name'\" | grep -q 1 || " .
                "sudo -u postgres psql -c \"CREATE DATABASE $name OWNER $user\"",
            "sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE $name TO $user\"",
        ]);

        if ($ok) {
            $this->success("PostgreSQL database '$name' created, user '$user' granted.");
        }

        return $ok;
    }

    private function configureSqlite(): bool
    {
        $dbDir  = base_path('database');
        $dbFile = "$dbDir/database.sqlite";

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        if (!file_exists($dbFile)) {
            touch($dbFile);
        }

        $this->success("SQLite database ready: $dbFile");
        return true;
    }

    private function writeDbEnv(string $envFile, string $driver, string $name, string $user, string $pass): void
    {
        if (!file_exists($envFile)) {
            return;
        }

        $connection = match ($driver) {
            'pgsql'  => 'pgsql',
            'sqlite' => 'sqlite',
            default  => 'mysql',
        };

        $this->replaceInEnv($envFile, 'DB_CONNECTION', $connection);

        if ($driver === 'sqlite') {
            $this->replaceInEnv($envFile, 'DB_DATABASE', base_path('database/database.sqlite'));
            return;
        }

        $port = $driver === 'pgsql' ? '5432' : '3306';
        $this->replaceInEnv($envFile, 'DB_HOST',     '127.0.0.1');
        $this->replaceInEnv($envFile, 'DB_PORT',     $port);
        $this->replaceInEnv($envFile, 'DB_DATABASE', $name);
        $this->replaceInEnv($envFile, 'DB_USERNAME', $user);
        $this->replaceInEnv($envFile, 'DB_PASSWORD', $pass);
    }

    private function generatePassword(): string
    {
        try {
            return bin2hex(random_bytes(12));
        } catch (\Random\RandomException) {
            return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 24);
        }
    }

    private function banner(): void
    {
        $this->line();
        $this->line('  ╔══════════════════════════════════════════╗');
        $this->info('  ║     Sofy Framework — Server Installer    ║');
        $this->line('  ╚══════════════════════════════════════════╝');
        $this->line();
    }
}
