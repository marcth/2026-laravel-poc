Validate, commit, push, and open a pull request for the current branch.

1. **Validate first** — run `/validate`. If any gate fails, stop and report. Do not proceed until all gates pass.

2. **Stage changes** — show the user a summary of changed files (`git status`). Ask the user to confirm before committing.

3. **Commit** — find the open GitHub issue linked to this branch (look for an issue number in the branch name or ask the user). Commit with a message referencing the issue:
   ```bash
   git add <staged files>
   git commit -m "<summary> (#<issue-number>)"
   ```

4. **Push**:
   ```bash
   git push origin <current-branch>
   ```

5. **Open PR** targeting `develop`:
   ```bash
   gh pr create --base develop --title "<issue title>" --body "<summary of changes, references #issue-number>"
   ```

Report the PR URL when done.
