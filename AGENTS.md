# Working rules

These rules apply to anyone changing this repository, human or AI.

## Before writing code

1. Read the design spec (`ds-NNN.md`) and technical spec (`ts-NNN-<name>.md`) for the work under `specs/`, and `docs/architecture.md`.
2. If anything in the spec is unclear, ask. Do not guess and build.
3. Check each assumption against the actual code before relying on it.

## Tests first

1. Turn each acceptance criterion into a named test before writing the code it covers. Start the test name with the AC ID.
2. Run the test and read the failure. It must fail for the reason it is meant to catch.
3. Write the code until the test passes, and no further.
4. Do not change a test to make it pass. Change a test only when the intended behaviour changed.
5. A test that expects a refusal also asserts that nothing was changed.

## While writing code

- Build what the technical spec lists, item by item. If you deviate, record why in its section 11.
- Change only the files the technical spec lists. A technical spec changes at most 15 files.
- Follow the layers in `docs/architecture.md`.
- Add no dependency that the spec does not list.
- Write a comment only when the reason cannot be read from the code.

## Before finishing

1. Run `./start.sh`. Every check must pass.
2. Update the spec so that it matches what was built.
3. Read the diff yourself before asking anyone else to.
