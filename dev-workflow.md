# Archaeopterix Development Workflow

This document defines the steps and workflow used for working with the repository on the project. The goal of this workflow is to ensure good quality code and to reduce the risk of introducing bugs and regressions. To do this, the process requires that any single line of code that is merged into the main development branch is reviewed by at least a second developer.

## Overview

We are using the [Git feature branch](https://www.atlassian.com/git/tutorials/comparing-workflows/feature-branch-workflow) with [Pull request](https://confluence.atlassian.com/display/STASH/Using+pull+requests+in+Stash) methodology according to which, each feature or bugfix is developed in its own separate branch.
These feature branches are created from the main development branch (develop) and ultimately merged into it once reviewed by at least on peer and any adjusment noted on that review is performed and verified.
No one commits directly to develop.
Master is only updated when a release is shipped so this branch always contains the latest released version.

## Naming conventions
**Branches** are named using the Jira the story or bug Code + an underscore + a brief description of what it does in ProperCase.
i.e. _US-01_ArticleOverlay_
or _US-1032_HomeHeroModule_

**Commits comments** must include the Jira code of the Story or bug they are addressing.
The first line will include JiraCode + a brief description in less than 50 chars, then an empty line, and finally an explanation of what was done and, if it applies, why.

Bug commit example
    
    BUG-9000 checkout form: Fix margin in small res

    When smartphone is rotated from landscape to portrait, the form element
     adds some odd spacing at the top
    - Increase the margin between title and ruler in 1 px


Story commit example
    
    US-8888 Cart preview: initial commit

    - Create component for cart preview
    - Create module for cart header
    - Create .sass for cart
    - Add basic styles for layout

## Start working on a feature or bug

This example assumes you are working on the Jira User Story US-165 "As a user, I can view the Article Overlay"

### 1) Create the feature branch locally based on the **latest from develop**
    
    > git checkout develop
    > git pull origin develop
    > git checkout -b US-165_ArticleOverlay

### 2) After finishing a consistent part of work, commit and push your changes

First, update your feature branch with the latest from develop.
Stash your local changes before you do that to avoid conflict merges.
Also, make sure you are on the correct branch

    > git status

    On branch US-165_ArticleOverlay
    (...)

    > git stash
    > git pull origin develop
    > git stash pop

Add files to stage and commit

    > git add public/-sass/modules/_article_overlay.small.scss
    > git add public/-sass/modules/_article_overlay.standard.scss
    > git commit
    (...create commit message)

Push your local commits creating a branch with the same name on the remote.

    > git push origin US-165_ArticleOverlay:US-165_ArticleOverlay


### 3) While working on a feature branch:
* **Pull often from develop** and **Stash your local changes before pulling**
This will reduce chances of long and painful conflict merges.
* **Group changes that address a specific issue on a single commit**
This contributes to a cleaner repository history and helps generating the release notes for QA.
* **Always use the Jira Code of the issue being addressed at the begining of the commit message**
Even if the branch was named after a User Story because more than one fixes are being worked on the same branch, use a separate commit for each fix.
Commits must be clearly documented in their comments and include the Jira Code of the relevant issue.

### 4) Create the pull request

1. Go to [the BitBucket Web interface for this project](https://bitbucket.org/mm-modular/suarios-archaeopterix/)
2. Go to the **Create Pull Request** action
3. Select **Source branch** (i.e. US-165_ArticleOverlay) and **Destination branch** (develop).
4. Select at least **2 reviewers** for your pull request and **Create pull request** The reviewers will receive a notification.
5. Reviewers will **review and comment** (or **approve and then merge**). The creator of the PR will receive notifications when a comment is added to the PR, when it is approved and when it is merged.
6. If any comments require a **change to the submitted code**, those changes need to be done on the local brach and pushed to the same remote branch that is the source for the pull request. (The PR will be automatically updated to include them)
7. **Reply to the comment once the adjustment is done** so the reviewer is notified and can verify the change.
