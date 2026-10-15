import template from './sw-profile-index-general.html.twig';

export default {
    template,

    inject: ['rl2faOnSave'],

    methods: {
        onSave() {
            this.rl2faOnSave();
        },
    },
};
