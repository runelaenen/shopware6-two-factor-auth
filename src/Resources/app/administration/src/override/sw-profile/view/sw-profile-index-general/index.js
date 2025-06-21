import template from './sw-profile-index-general.html.twig';

export default {
    template,

    methods: {
        onSave() {
            this.$emit('rl-2fa-save');
        }
    }
};
