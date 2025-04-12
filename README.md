# Web Technologies Project

[English](README.md) | [Ελληνικά](README_gr.md)

## How to build and run

1. Download and install Docker Desktop

    > Windows: WSL is required for Docker to work. [Instructions here...](https://learn.microsoft.com/en-us/windows/wsl/install#change-the-default-linux-distribution-installed)

2. Clone project

    ```bash
    git clone https://github.com/maikkundev/webtech.git
    ```

3. Navigate to the project through your terminal

    ```bash
    cd ~/projects/webtech # Example
    ```

4. Build and run via Docker

    ```bash
    docker compose up --build -d # -d: detached
    ```

5. Visit the app on `localhost:80`

## How to contribute

1. Make a new branch from `main`

    ```bash
    git checkout main # Make sure you are on the main branch
    git pull # Make sure you have the latest changes

    git checkout -b feature/YOUR_NAME/your-feature-name # For new features. Example: feature/maikkundev/awesome-feature. -b: create a new branch

    # OR
    
    git checkout -b bugfix/YOUR_NAME/your-bug-name # For bug fixes. Example: bugfix/maikkundev/fix-issue-123. -b: create a new branch
    ```

2. Commit your changes

    ```bash
    git add . # Add all changes to the staging area
    git commit -m "Your commit message" # Commit your changes with a message. Example: "Added support for light/dark mode"
    ```

    > Note: Use `git status` to check the status of your branch and see which files have been changed.

3. After making and committing your changes, push the branch to GitHub

    ```bash
    git push origin [BRANCH_NAME] # Example: git push origin feature/maikkundev/new-feature
    ```

4. Create a pull request from your branch to `main`

    1. Go to [Pull Requests](https://github.com/maikkundev/webtech/pulls)
    2. Click on `New Pull Request`
    3. Select your branch from the dropdown menu
    4. Add a title and describe your changes
    5. Click on `Create Pull Request`
    > Note: you can still push changes to your branch after creating the pull request. The pull request will automatically update with your changes.
