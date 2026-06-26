Create a GitHub issue from a plan file and a matching feature branch.

1. List the plan files in `.omc/plans/` and ask the user to select one.
2. Read the selected plan file and extract its title and objective.
3. Ask the user to choose a branch type prefix: `feature/`, `fix/`, `chore/`, `docs/`, or `refactor/`.
4. Create the GitHub issue using `gh issue create`:
   ```bash
   gh issue create --title "<title>" --body "<objective and scope from plan>"
   ```
5. Note the issue number returned.
6. Derive a slug from the plan title (lowercase, hyphens).
7. Create and checkout the branch:
   ```bash
   git checkout -b <type>/<slug>
   git push -u origin <type>/<slug>
   ```

Report the issue URL and the branch name when done.
