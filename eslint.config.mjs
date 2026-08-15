import { createConfigForNuxt } from '@nuxt/eslint-config/flat'

export default createConfigForNuxt({
    features: {
        stylistic: {
            braceStyle: '1tbs',
            indent: 4,
            commaDangle: 'never'
        }
    }
})
    .append({
        rules: {
            '@stylistic/space-before-function-paren': ['error', 'always'],
            'vue/multi-word-component-names': 'off'
        }
    })
