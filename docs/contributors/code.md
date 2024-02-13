# Welcome to the Development Contribution Guide
Here you'll find information on how to get started with contributing code to the project.  
We also welcome non-code contributions, such as [documentation](https://github.com/amnestywebsite/humanity-image-credit/blob/main/docs/contributors/docs.md), [triage](https://github.com/amnestywebsite/humanity-image-credit/blob/main/docs/contributors/triage.md), and [testing](https://github.com/amnestywebsite/humanity-image-credit/blob/main/docs/contributors/a11y.md).  

## Getting Started
This guide assumes you have an available Virtual Host or Docker environment through which you can run the project locally.  

### Prerequisites
- [`PHP`](https://www.php.net/) v8.2.*  
- [`Composer`](https://getcomposer.org/) v2+  

#### Setting Up
- Clone the repo into the plugins directory.  
- Install PHP dependencies: `composer install`  
  This step installs our [PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer) toolchain, and will allow you to scan your code changes for any stylistic incompatibilities. It's important to run PHPCS (`composer lint`) prior to creating any Pull Requests, as PRs are auto-rejected if any issues are found.  
- Download any Required or Recommended Plugins. Follow plugin instructions for installation steps.  

### Submitting Issues or Feature Requests
When submitting tickets, please give a detailed description of the issue in question along with where the issue was found; steps to replicate; and, where possible, a screenshot of the issue.  

When requesting a feature, create a discussion in the same way you would an issue, minus the steps to replicate and screenshot; a detailed description of the feature, and your expectations, is required.  

## Working with Issues
When working on an issue, assign yourself to the ticket, and be sure to update the status of the ticket so that it moves along the project board e.g. (To Do, In Progress, PR Created etc).  

### Branching
Branches should have short, relevant names, and be prefixed with their type. e.g. `feature/main-nav`, `hotfix/menu-z-index`, `chore/package-update`.  
All branches should be taken from `main`.  
All branches should be Pull Requested into `develop`.  
No code should be committed directly to `main`, `staging`, or `develop`.  

Primary branches are listed in the [branch model](#branch-model) section.  

### Developing Code
Once you have the project installed and set up, and you have branched off from `main` onto your task branch, you're ready to start coding. There are few things we'd appreciate you bearing in mind whilst writing code, and we provide tools which should help you with that.  
We have strict stylistic requirements PHP. To make these requirements easier to implement, we provide a linting tool (see [Before Setup](#before-setup)) you can run using `composer lint`.  You can automate this through configuring your code editor.  

### Before Committing
Prior to committing any code, please ensure that `composer lint` reports no errors. This will save time in the long run.  

#### Commit Messages
We follow the commit message standards outlined in detail in [this excellent post](https://cbea.ms/git-commit/) by CBEAMS.  

### Creating Pull Requests
Pull Requests should always be made to `develop`.  
Please include the following detail:  
- A link to the GitHub Issue  
- A brief description of what the code in the PR does  
- Information on how to test the changes made by the PR  
- Video demonstrating the code  

## Branch Model

### `main`
The "source of truth". Any and all branches should be created using `main` as a base.  
This branch auto-deploys to GitHub Releases (draft).  
Only branches that meet all of the following criteria should be merged into `main`:  
- Pull Request has been approved  
- Internal Testing has passed  
- UAT has passed  
- Release Candidate has been signed off  

### `staging`
Only branches that meet all of the following criteria should be merged into `staging`:  
- Pull Request has been approved  
- Internal Testing has passed  

### `develop`
Target branch for Pull Requests.  
Only branches that have met all of the following criteria should be merged into `develop`:  
- Successful local environment test by code owner  
- Pull Request has been approved  

## Releases

As mentioned in the [Branch Model](#branch-model) section, commits to [`main`](#main) trigger a build in Travis CI that creates a draft release in GitHub. Only one draft can be present at a time, so new commits will overwrite the existing draft.  

When creating a new release, the final commit to `main` should contain the appropriate version bump in [`/humanity-image-credit/wp-plugin-image-credit.php`](https://github.com/amnestywebsite/humanity-image-credit/blob/main/humanity-image-credit/wp-plugin-image-credit.php), and an update to the changelog at [`/CHANGELOG.md`](https://github.com/amnestywebsite/humanity-image-credit/blob/main/CHANGELOG.md).  

The tag for the release — and the release title — should match the version specified in both the changelog and stylesheet. The release notes should match those in the changelog. See existing releases for examples.  
