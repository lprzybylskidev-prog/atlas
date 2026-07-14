import pluginVue from 'eslint-plugin-vue';
import pluginTailwindcss from 'eslint-plugin-tailwindcss';
import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: ['public/build/**', 'vendor/**', 'node_modules/**'],
    },
    ...pluginVue.configs['flat/recommended'],
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        files: ['resources/js/**/*.{ts,vue}'],
        plugins: {
            tailwindcss: pluginTailwindcss,
        },
        settings: {
            tailwindcss: {
                cssConfigPath: 'resources/css/app.css',
            },
        },
        rules: {
            '@typescript-eslint/consistent-type-imports': ['error', { prefer: 'type-imports' }],
            '@typescript-eslint/no-explicit-any': 'error',
            'tailwindcss/no-contradicting-classname': 'error',
            'vue/attributes-order': 'off',
            'vue/html-indent': 'off',
            'vue/html-self-closing': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/multi-word-component-names': 'off',
            'vue/singleline-html-element-content-newline': 'off',
        },
    },
];
