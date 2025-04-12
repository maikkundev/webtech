# Web Technologies Project

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
