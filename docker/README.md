TO setup this image using docker, you will need to rebuild the image using the docker-compose build command.
The build requires a SSH KEY setup to connect to the github repo, and has currently the names bot_github_prod8 / bot_github_prod8.pub in the Dockerfile. 
Please rename accordingly to your files.
You can copy the .env.example to .env, and the docker-compose.example.yml to docker-compose.yml to start customizing the files.

The DB host can either be the service name or the container name of the DB container.
