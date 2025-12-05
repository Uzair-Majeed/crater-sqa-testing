pipeline {
    agent any

    environment {
        GITHUB_CREDENTIALS = 'Jenkins-Token'
        PHP_MEMORY_LIMIT = '2000M'
        APP_ENV_FILE = '.env.workflow'
        DB_FILE = 'database\\database.sqlite'
    }

    stages {
        stage('Checkout') {
            steps {
                echo "Checking out code..."
                checkout scm
            }
        }

        stage('Install PHP & Composer Dependencies') {
            steps {
                echo "Setting up PHP 8.1 and Composer dependencies..."
                bat 'php -v'
                bat 'composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader'
            }
        }

        stage('Prepare Environment & Database') {
            steps {
                echo "Setting up .env and SQLite database..."
                // Copy workflow env file
                bat "copy ${APP_ENV_FILE} .env"

                // Ensure database folder exists
                bat 'if not exist database mkdir database'

                // Create SQLite database file if not exists
                bat "if not exist ${DB_FILE} type nul > ${DB_FILE}"

                // Clear config cache
                bat 'php artisan config:clear'

                // Run migrations & seed demo data
                bat 'php artisan migrate --force --seed'
                bat 'php artisan db:seed --class=DemoSeeder --force'
            }
        }

        stage('Run Integration Tests') {
            steps {
                echo "Running integration tests..."
                bat "php -d memory_limit=${PHP_MEMORY_LIMIT} vendor\\bin\\pest.bat tests\\Integration-Testing"
            }
        }

        stage('Run Unit Tests & Generate Coverage') {
            steps {
                echo "Running unit tests and generating coverage..."
                bat "php -d memory_limit=${PHP_MEMORY_LIMIT} vendor\\bin\\pest.bat tests\\Unit-Testing --coverage --coverage-html=build\\coverage --coverage-xml=build\\test-results"
            }
            post {
                always {
                    echo "Archiving test results and coverage..."
                    junit 'build/test-results/*.xml'
                    archiveArtifacts artifacts: 'build/coverage/**', allowEmptyArchive: true
                }
            }
        }

        stage('Run Cypress Tests (Placeholder)') {
            steps {
                echo "Cypress tests would run here..."
                // Uncomment when Node.js + Cypress are installed
                // bat 'npx cypress run'
                
            }
        }
    }

    post {
        success {
            echo "Pipeline succeeded!"
        }
        failure {
            echo "Pipeline failed!"
        }
        always {
            echo "Pipeline finished."
        }
    }
}
