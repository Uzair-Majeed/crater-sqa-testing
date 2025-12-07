module.exports = {
    ci: {
        collect: {
            url: [
                'https://crater-sqa-testing-production.up.railway.app/login',
                'https://crater-sqa-testing-production.up.railway.app/installation',
            ],
            numberOfRuns: 3,
            settings: {
                chromeFlags: '--no-sandbox --disable-dev-shm-usage',
            },
        },
        assert: {
            assertions: {
                'categories:performance': ['warn', { minScore: 0.7 }],
                'categories:accessibility': ['error', { minScore: 0.9 }],
                'categories:best-practices': ['warn', { minScore: 0.8 }],
                'categories:seo': ['warn', { minScore: 0.7 }],
            },
        },
        upload: {
            target: 'temporary-public-storage',
        },
    },
};
