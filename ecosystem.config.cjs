module.exports = {
  apps: [
    {
      name: 'numen-laravel',
      script: 'php',
      args: 'artisan serve --host=0.0.0.0 --port=8000',
      cwd: '/home/node/.openclaw/workspace/projects/numen',
      autorestart: true,
      max_restarts: 50,
      restart_delay: 2000,
      watch: false,
      log_file: '/tmp/numen-laravel.log',
      error_file: '/tmp/numen-laravel-error.log',
      out_file: '/tmp/numen-laravel-out.log',
    },
    {
      name: 'numen-proxy',
      script: 'proxy.mjs',
      cwd: '/home/node/.openclaw/workspace/projects/numen',
      autorestart: true,
      max_restarts: 50,
      restart_delay: 2000,
      watch: false,
      log_file: '/tmp/numen-proxy.log',
      error_file: '/tmp/numen-proxy-error.log',
      out_file: '/tmp/numen-proxy-out.log',
    },
  ],
};
