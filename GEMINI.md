# Project Overview

This is a web-based dashboard application built with the Laravel PHP framework. It displays various information tiles, such as weather, social media mentions, team status, and more. The frontend is built using Vue.js and Tailwind CSS.

## Building and Running

To get the application up and running, follow these steps:

1.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

2.  **Install frontend dependencies:**
    ```bash
    npm install
    # or
    yarn
    ```

3.  **Compile frontend assets:**
    ```bash
    npm run dev
    # or
    yarn dev
    ```

4.  **Set up environment variables:**
    *   Copy `.env.example` to `.env`.
    *   Generate an application key:
        ```bash
        php artisan key:generate
        ```
    *   Fill in the rest of the required values in the `.env` file (database credentials, etc.).

5.  **Run database migrations and seeders:**
    ```bash
    php artisan migrate --seed
    ```

6.  **Start the queue listener and scheduler.**

## Development Conventions

*   **Backend:**
    *   The backend follows standard Laravel conventions.
    *   Service providers are used to register services (e.g., `GitHubServiceProvider`, `SlackServiceProvider`).
    *   Tests are written with PHPUnit and can be found in the `tests` directory.

*   **Frontend:**
    *   Frontend assets are managed with Laravel Mix.
    *   The styling is done with Tailwind CSS.
    *   JavaScript tests are written with Jest and can be found in the `resources/__tests__` directory.
    *   The main view is `resources/views/dashboard.blade.php`.
