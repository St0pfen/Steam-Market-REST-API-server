---
name: Bug Report
about: Create a report to help us improve the Steam Market REST API
title: '[BUG] '
labels: ['bug']
assignees: ''
---

## 🐛 Bug Description

A clear and concise description of what the bug is.

## 🔄 Steps to Reproduce

1. Go to '...'
2. Call endpoint '...'
3. With parameters '...'
4. See error

## ✅ Expected Behavior

What you expected to happen.

## ❌ Actual Behavior

What actually happened.

## 📋 Environment Information

**Server Environment:**
- PHP Version: [e.g. 8.2.0]
- Operating System: [e.g. Ubuntu 22.04]
- Web Server: [e.g. Apache, Nginx, PHP Built-in]

**Client Environment:**
- Browser: [e.g. Chrome 118]
- Framework: [e.g. React, Vue, vanilla JS]

## 🔍 Error Details

**API Response:**
```json
{
  "error": "paste error response here",
  "success": false
}
```

**HTTP Status Code:** [e.g. 500, 404, 400]

## 📡 Request Details

**Endpoint:** `GET /api/v1/steam/endpoint`

```bash
# cURL command that reproduces the issue
curl -X GET "http://localhost:8000/api/v1/steam/endpoint" \
     -H "Accept: application/json"
```

## 📝 Additional Context

Any other context about the problem.

## ✅ Checklist

- [ ] I have searched for existing issues
- [ ] I have tested with the latest version
- [ ] I have provided a minimal test case
