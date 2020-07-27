import PluginManager from 'src/plugin-system/plugin.manager';
import Rl2faVerificationPlugin from "./plugin/rl2fa-verification.plugin";

PluginManager.register('Rl2faVerificationPlugin', Rl2faVerificationPlugin, '[data-rl2fa-verification-plugin]');
