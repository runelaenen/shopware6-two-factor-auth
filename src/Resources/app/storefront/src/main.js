window.PluginManager.register(
    'Rl2faVerificationPlugin',
    () => import('./plugin/rl2fa-verification.plugin'),
    '[data-rl2fa-verification-plugin]'
);
