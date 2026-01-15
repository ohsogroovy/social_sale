import concurrently from 'concurrently';
concurrently(
  [
    { command: 'php artisan serve', name: 'server' },
    { command: 'npm run dev', name: 'frontend' },
    { command: 'expose share --subdomain=social-sale localhost:8000', name: 'expose' },
    { command: 'php artisan queue:listen', name: 'worker' },
    { command: 'php artisan schedule:work', name: 'scheduler' },
  ],
  {
    prefix: 'time',
    restartTries: 0,
    prefixColors: "auto"
  },
);