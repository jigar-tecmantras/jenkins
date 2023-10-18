pipeline {
    agent any
    stages {
        stage("Clone Git Repository") {
            steps {
                git(
                    url: "https://github.com/jigar-tecmantras/jenkins.git",
                    branch: "master",
                    changelog: true,
                    poll: true
                )
            }
        }
        stage("Create artifacts or make changes") {
            steps {
                sh "touch testfile"
                sh "git add testfile"
                sh "git commit -m 'Add testfile from Jenkins Pipeline'"
            }
        }
        stage("Push to Git Repository") {
            steps {
                script {
                    withCredentials([gitUsernamePassword(credentialsId: '96e32ab9-c797-4ebf-9c42-8d1f6eade30c', gitToolName: 'Default')]) {
                        sh "git push -u origin master"
                    }
                }
            }
        }
         stage('Build') {
            steps {
                sh 'your-build-command'
            }
        }
        stage('Test') {
            steps {
                sh 'your-test-command'
            }
        }
        stage('Deploy') {
            steps {
                sh 'your-deployment-command'
            }
        }
    }
    post {
        always {
            deleteDir()
        }
    }
}
