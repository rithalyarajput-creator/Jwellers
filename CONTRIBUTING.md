# Contributing to Foreverkids

This is the rulebook. **No exceptions** — they are how we got the 165-file dirty
working tree, the production hotfix-by-SSH culture, and the broken Nia webhook
in April. Follow them or your PR will be closed.

---

## TL;DR — the loop

```
1. git checkout main && git pull
2. git checkout -b feat/<short-name>     ← never work on main
3. ...edit, commit small, commit often...
4. git push -u origin feat/<short-name>
5. open PR on GitHub → wait for CI green → request review
6. reviewer approves → merge via "Squash and merge"
7. deploy workflow runs automatically with manual approval gate
```

---

## Branch strategy

| Branch        | Purpose                                  | Who pushes here       |
|---------------|------------------------------------------|-----------------------|
| `main`        | Production. Every commit deploys.        | **Nobody directly.** Only via merged PR. |
| `develop`     | Staging mirror (when staging exists).    | Only via merged PR.   |
| `feat/*`      | New features                             | Authors               |
| `fix/*`       | Bug fixes                                | Authors               |
| `hotfix/*`    | Urgent production-only fixes             | Authors (still PR'd)  |
| `chore/*`     | Refactors, tooling, docs                 | Authors               |

**Rule:** branch protection on `main` requires:
- Pull request before merge
- ≥ 1 approving review from a `CODEOWNERS` reviewer
- All CI checks green
- Branch up to date with `main` before merge
- Linear history (squash or rebase, no merge commits)

---

## Commit hygiene

- **Small commits.** One logical change per commit. Reviewer should be able to
  read each commit independently.
- **Imperative subject line, ≤ 72 chars.** "Add product card variant picker",
  not "added the new variant picker thing".
- **Body explains *why*, not *what*.** The diff shows what.
- **Reference the issue / context** if one exists: `Fixes #123`.
- **Never commit:**
  - Secrets (`.env`, `*.pem`, `*.key`, API tokens). Gitleaks will block you.
  - Generated assets (`public/build/`, `node_modules/`, `vendor/`). All gitignored.
  - Personal scratch files (`storage/tmp_*`, `*.bak`). Gitignored.
  - Big binary dumps (annual `.xls` exports, image archives). Gitignored.

---

## Pull request rules

- **Title:** same conventions as commit subjects.
- **Description must include:**
  - **Summary:** 2-3 bullets on what changed and why
  - **Test plan:** how to verify in browser/staging — explicit URLs and steps
  - **Risk:** "Low / Medium / High" + what could break
  - **Migrations:** if any, note whether they're reversible and what data they touch
  - **Screenshots:** for any UI change (before / after)
- **Size:** keep under 400 lines diff. Bigger? Split it. Reviewer fatigue is
  the #1 source of bugs that ship.
- **CI must be green** before requesting review. Don't ask someone to review
  red builds.
- **One reviewer minimum.** Tag the relevant CODEOWNER + anyone whose code
  you touched. UI changes also tag QA (Tara).

---

## Test ratchet — how the test suite works in CI

Foreverkids uses a **test ratchet** rather than "all tests must pass".
That's because the test suite has accumulated regressions over time and
greening it up is a multi-week project owned by Tara, not a blocker for
shipping product work.

The rules:

1. CI runs the full PHPUnit suite on every PR.
2. A list of currently-passing tests is committed to
   `tests/baseline-passing.txt`.
3. **Your PR must not cause any baseline test to fail.** That is the
   regression gate.
4. Tests that are *not* in the baseline are allowed to fail — they're
   tracked as "known broken" and being fixed by Tara's team.
5. If your PR fixes a previously-broken test, the ratchet will print
   that as "free wins". Run `python3 scripts/ci/test_ratchet.py
   storage/logs/junit.xml tests/baseline-passing.txt --update` to
   regenerate the baseline, commit the change, and lock in the gain.
6. **The baseline can only grow.** Removing tests from it requires CTO
   sign-off, and only when the test itself is being deleted (not when
   it's flaky and we're tired of fixing it).

Why this approach: a CI that gates on "all 163 tests pass" when 61 of
them are bit-rotted just blocks every PR forever. A ratchet gives us
the regression protection we need today AND a measurable path to
full-suite green over the coming weeks.

See `doc/devops-runbook.md` § "Test ratchet" for the operator's view.

---

## Code standards

These are mandated by the CEO. Non-negotiable.

- **CMM Level 5 coding standards** — see `doc/cmm-level5-standards.md`
  - 300 lines max per file (controllers, services, blade components)
  - Semantic HTML in all blade templates (no `<div soup>`)
  - Variable contracts: every public method has typed args + return type
  - Security-first: validate all input at the boundary, never trust the request
- **Frontend testing standards 2026** — see `doc/frontend-testing-standards.md`
  - UI changes require browser-verified test plan in PR
  - No "looks good on my localhost" — must work on staging URL
  - Performance benchmarks (LCP, CLS) noted in PR for any page-level change

---

## When CI fails

| Failure         | Action                                                                |
|-----------------|-----------------------------------------------------------------------|
| Pint            | Run `vendor/bin/pint` locally and commit the fixes.                   |
| PHPUnit         | Run `php artisan test` locally. Fix the test or fix the code.         |
| Vite build      | Run `npm run build` locally. Usually a missing import or syntax err.  |
| Gitleaks        | **Stop.** A secret leaked. Rotate it immediately, then scrub history. |

---

## Production deploys

You **do not** deploy to production. The pipeline does. Specifically:

1. PR merges into `main`
2. `.github/workflows/deploy.yml` triggers
3. Pre-flight waits for CI to pass on the merge commit
4. Job pauses for manual approval (Settings → Environments → production)
5. Approver clicks "Review deployments" → Approve
6. SSH to EC2 → `scripts/deploy/deploy.sh` runs
7. Smoke test hits `https://foreverkidss.in/`
8. Slack notification fires (if webhook configured)

If you SSH into the EC2 box and `git pull` manually, you have broken the chain
of custody. The next deploy will fail because the working tree is dirty.
**Don't do it.** Use the workflow.

---

## Hotfix flow

Real hotfix, prod is down or losing money:

```
1. git checkout main && git pull
2. git checkout -b hotfix/<short-name>
3. Make the smallest possible fix
4. Push, open PR with title prefix [HOTFIX], tag CTO + DevOps Head
5. Reviewer approves in <30 min on Slack
6. Merge → deploy workflow → manual approval → live
7. Post-mortem within 48h (open RCA in Team AI)
```

Never edit files directly on the EC2 box, even in a hotfix. The 30 minutes you
"saved" will cost a week of cleanup later.

---

## Questions

- DevOps / pipeline / SSH access → **Ravi** (Head of DevOps)
- Code review / standards → **Leo** (CTO)
- QA / test plans → **Tara** (QA Lead)
- Process / SOP → **Kavita** (Process Excellence)
- Anything else → **Shivam** (COO)
