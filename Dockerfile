FROM php:7.4.25

WORKDIR /app

# poppler-utils has pdftohtml
RUN apt-get update
RUN apt-get install poppler-utils -y

# install node via nvm
ENV NODE_VERSION=14.18.1
RUN apt install -y curl
RUN curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.34.0/install.sh | bash
ENV NVM_DIR=/root/.nvm
RUN . "$NVM_DIR/nvm.sh" && nvm install ${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm use v${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm alias default v${NODE_VERSION}
ENV PATH="/root/.nvm/versions/node/v${NODE_VERSION}/bin/:${PATH}"
RUN node --version
RUN npm --version

# install dependencies
COPY package*.json ./
RUN npm ci --only=production && npm cache clean --force

# setup project files
COPY . /app

# GCR port
EXPOSE 8080

# Let express app know this is a container
ENV IS_CONTAINER=true

# start the server
CMD [ "npm", "start" ]
