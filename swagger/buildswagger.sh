#!/bin/bash

###
# make sure openapi-generator-cli is installed
npm install

###
# build and collect in dir
rm -rf upload
mkdir upload

###
# build the docs in the upload directory
npx @openapitools/openapi-generator-cli generate -i ./swagger.yaml  -g openapi  -o upload
cp index.html upload/
