pipeline {
    agent any

    // Trigger on GitHub push or PR
    triggers {
        githubPush()
    }

    environment {
        GITHUB_CREDENTIALS = 'JenkinsPAT'
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
                sh 'composer install --no-interaction --no-progress'
            }
        }

        stage('Run Unit Tests') {
            steps {
                echo "Running backend unit tests..."
                sh './vendor/bin/pest'
            }
            post {
                always {
                    echo "Unit tests completed."
                }
            }
        }

        stage('Build') {
            steps {
                echo "Build stage complete (no artifacts yet)."
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
