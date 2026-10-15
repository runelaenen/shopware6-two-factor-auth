/**
 * Exposes the profile page save action to the child views rendered by the
 * page's <router-view>, so the 2FA card can trigger a save without this plugin
 * having to copy and override the core `sw_profile_index_router_view` block.
 */
export default {
    provide() {
        return {
            rl2faOnSave: () => this.onSave(),
        };
    },
};
