export default {
    rules: {
        'at-rule-no-unknown': [
            true,
            {
                ignoreAtRules: ['import', 'source', 'theme', 'custom-variant', 'layer'],
            },
        ],
    },
};
