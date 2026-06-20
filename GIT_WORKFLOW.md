# Daily Git Workflow — FYP Group Repo

How to handle daily code changes without stepping on your teammates. Branch + Pull Request flow.

---

## BEFORE you start coding

1. **Switch to main and pull the latest changes**
   ```bash
   git checkout main
   git pull origin main
   ```
   Do this every time, even if you "didn't touch main yesterday" — someone else might have merged something.

2. **Create a new branch for today's work**
   ```bash
   git checkout -b feature/short-description
   ```
   Naming examples:
   - `feature/srs-section-3`
   - `fix/login-validation-bug`
   - `chore/update-readme`

   One branch = one task. Don't pile unrelated changes into the same branch.

3. **Confirm you're on the right branch**
   ```bash
   git branch
   ```
   The branch with `*` next to it is your current one.

---

## DURING coding

4. **Commit in small, logical chunks** — don't wait until the end of the session to commit everything at once.
   ```bash
   git add .
   git commit -m "Add player KDA calculation logic"
   ```
   Good commit messages: short, present tense, describe *what* changed.
   - ✅ `Fix null pointer on empty match data`
   - ❌ `update`, `fix stuff`, `asdkjasd`

5. **Check status often** if you're not sure what's changed
   ```bash
   git status
   git diff
   ```

6. **Push your branch regularly** (not just at the end) — this backs up your work and lets teammates see progress
   ```bash
   git push origin feature/short-description
   ```
   First push on a new branch may need:
   ```bash
   git push -u origin feature/short-description
   ```

---

## AFTER coding (end of session)

7. **Pull main again before merging** — in case teammates merged something while you worked
   ```bash
   git checkout main
   git pull origin main
   git checkout feature/short-description
   git merge main
   ```
   Resolve any conflicts now, while the changes are fresh in your head — not next week.

8. **Final commit and push**
   ```bash
   git add .
   git commit -m "Final commit for the day: SRS section 3 draft"
   git push origin feature/short-description
   ```

9. **Open a Pull Request (PR) on GitHub**
   - Go to the repo → "Compare & pull request"
   - Title: what the branch does
   - Description: what changed, why, anything reviewers should check
   - Tag a teammate to review if your group requires it

10. **Merge only after review** (or solo-approve if your group's rule allows same-day merge for low-risk changes)
    - Use **Squash and merge** to keep main's history clean, unless your group prefers full commit history
    - Delete the branch after merging (GitHub gives you a button for this)

11. **Sync everyone**
    - Tell the group chat what got merged into `main`, especially if it touches shared files (models, config, `.env.example`, migrations)
    - If you changed the database schema, flag it immediately — teammates need to run migrations locally too

---

## Quick command cheat sheet

| Action | Command |
|---|---|
| Update main | `git checkout main && git pull origin main` |
| New branch | `git checkout -b feature/name` |
| Check changes | `git status` / `git diff` |
| Stage + commit | `git add . && git commit -m "message"` |
| Push branch | `git push origin feature/name` |
| Merge main into your branch | `git merge main` |
| Undo last commit (keep changes) | `git reset --soft HEAD~1` |
| Discard local changes to a file | `git checkout -- filename` |
| See commit history | `git log --oneline -10` |

---

## Group rules to agree on (fill in for your team)

- [ ] Branch naming convention: ____________________
- [ ] Who reviews PRs before merge: ____________________
- [ ] Merge strategy (squash / merge commit / rebase): ____________________
- [ ] What requires a teammate heads-up before merging (e.g. migrations, shared components): ____________________
- [ ] Code freeze date for FYP submission: ____________________

---

## Common mistakes to avoid

- Committing directly to `main` — always work in a branch
- Force-pushing (`git push -f`) on a shared branch — this can wipe teammates' commits
- Letting a branch live for weeks without merging — conflicts get worse, not better, over time
- Vague commit messages that mean nothing a week later
- Merging without pulling `main` first


