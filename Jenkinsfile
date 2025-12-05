pipeline {
    agent any

    environment {
        GITHUB_CREDENTIALS = 'Jenkins-Token'
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
                echo "Setting up PHP 8.1 with required extensions..."
                // Assuming PHP 8.1 is installed on the Jenkins agent
                bat 'php -v'
                bat 'composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader'
            }
        }

        stage('Prepare Environment & Database') {
            steps {
                echo "Copying environment file and preparing SQLite DB..."
                bat 'copy .env.workflow .env'
                bat 'type nul > database\\database.sqlite'
                bat 'php artisan config:clear'
                bat 'php artisan migrate --force --seed'
                bat 'php artisan db:seed --class=DemoSeeder --force'
            }
        }

        stage('Run Integration Tests') {
            steps {
                echo "Running integration tests..."
                bat 'php -d memory_limit=2000M vendor\\bin\\pest.bat tests\\Integration-Testing'
            }
        }

        stage('Run Unit Tests & Generate Coverage') {
            steps {
                echo "Running unit tests and generating coverage report..."
                bat 'php -d memory_limit=2000M vendor\\bin\\pest.bat tests\\Unit-Testing --coverage --coverage-html=build\\coverage --coverage-xml=build\\test-results'
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
