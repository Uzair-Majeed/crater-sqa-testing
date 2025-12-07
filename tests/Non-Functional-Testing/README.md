# Non-Functional Testing Guide - Production Deployment

**Application URL:** https://crater-sqa-testing-production.up.railway.app  
**Pages Tested:** Login and Installation (Public pages)

---

## Automated Testing with Lighthouse CI

### Pages Tested

✅ **Login Page:** `/login`  
✅ **Installation Page:** `/installation`

### Run Tests

```bash
lhci autorun
```

This will test both pages and generate performance, accessibility, best practices, and SEO reports.

---

## View Results

### Lighthouse Report

After running tests, the report URL will be displayed in the console and saved to:
- `.lighthouseci/links.json` - Contains report URLs
- `.lighthouseci/` directory - Contains full HTML reports

### Scores to Check

| Category | Target | Good | Needs Improvement |
|----------|--------|------|-------------------|
| Performance | 70+ | 80+ | < 70 |
| Accessibility | 90+ | 95+ | < 90 |
| Best Practices | 80+ | 90+ | < 80 |
| SEO | 70+ | 85+ | < 70 |

---

## Security Testing (Optional)

### OWASP ZAP Scan

```bash
docker run -v %cd%/tests/Non-Functional-Testing:/zap/wrk/:rw -t zaproxy/zap-stable \
  zap-baseline.py -t https://crater-sqa-testing-production.up.railway.app \
  -r security-report.html
```

**Duration:** 10-15 minutes

---

## Quick Start

1. **Run Lighthouse tests:**
   ```bash
   lhci autorun
   ```

2. **View report link** in console output

3. **Take screenshots** of:
   - Overall scores
   - Performance metrics
   - Accessibility violations

4. **Done!** ✅

---

## Test Results Location

- **Lighthouse Reports:** `.lighthouseci/` directory
- **Report URLs:** `.lighthouseci/links.json`
- **Test Summary:** `test-results.md`

---

## Estimated Time

- **Automated Tests:** 2-3 minutes
- **Screenshot Capture:** 5 minutes
- **Total:** ~10 minutes
