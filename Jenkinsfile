pipeline {
    agent any

    // Trigger on GitHub push or PR
    triggers {
        githubPush()
    }

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

        stage('Install Backend Dependencies') {
            steps {
                echo "Installing PHP dependencies..."
                // Ensure composer is in PATH
                bat 'composer install --no-interaction --no-progress'
            }
        }

        stage('Run Backend Unit Tests') {
            steps {
                echo "Running backend unit tests..."
                // Use Windows path separator
                bat 'vendor\\bin\\pest.bat'
            }
            post {
                always {
                    echo "Backend unit tests completed."
                }
            }
        }

        stage('Build') {
            steps {
                echo "Build stage complete (no artifacts yet)."
                // Placeholder for any future build steps
            }
        }

        stage('Run Frontend/UI Tests') {
            steps {
                echo "Running UI tests (Cypress placeholder)..."
                // Uncomment once Node.js + Cypress are installed
                // bat 'npx cypress run'
            }
            post {
                always {
                    echo "UI tests completed."
                }
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
