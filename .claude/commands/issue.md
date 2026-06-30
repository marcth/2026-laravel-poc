Create a GitHub issue from a plan file and a matching feature branch.

> Note: `.omc/plans/` is gitignored — plan files are local only. The GitHub issue IS the persistent record of the plan. Always embed the full plan content in the issue body so it is visible to all collaborators on GitHub.

1. List the plan files in `.omc/plans/` and ask the user to select one.
2. Read the selected plan file in full.
3. Extract the title from the plan's first heading.
4. Ask the user to choose a branch type prefix: `feature/`, `fix/`, `chore/`, `docs/`, or `refactor/`.
5. Create the GitHub issue using `gh issue create`, embedding the **full plan content** as the body:
   ```bash
   gh issue create --title "<title>" --body "<full plan file content>"
   ```
6. Note the issue number returned.
7. Derive a slug from the plan title (lowercase, hyphens).
8. Create and checkout the branch:
   ```bash
   git checkout -b <type>/<slug>
   git push -u origin <type>/<slug>
   ```

Report the issue URL and the branch name when done.
