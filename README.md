# pdf-parser

Docker for creating a PDF parsing server

## Local Setup

1. `git clone git@github.com:WriterDuetTeam/pdf-parser.git`
2. `cd pdf-parser`
3. `npm install`

### Run and test locally OUTSIDE of Docker

1. `npm run dev`
2. Visit `http://localhost:8080/test`
3. Choose a `.pdf` file.
4. Wait and see the response from `POST /convert_script`

Having issues? See initial notes from Guy below.

### Run and test locally INSIDE of Docker

1. `npm run docker:dev`
2. Visit `http://localhost:8080/test`
3. Choose a `.pdf` file.
4. Wait and see the response from `POST /convert_script`

### Test against a test GCR instance

1. Run `npm run dev`.
2. Visit `http://localhost:8080/test`
3. Update the Api Url to `https://pdf-parser-ki7n3lc5eq-wn.a.run.app`. TODO remove this!
4. Choose a `.pdf` file.
5. Wait and see the response from `POST /convert_script`

## PDF Conversion Flow

1. User goes to File / Import and selects to import a `.pdf` file.
2. WD sends `POST` request to GCR with `form-data`...

```text
ext: pdf
agree_tou: true
format: fdx
download: true
script: (binary)
```

Full curl example below.

```text
curl 'http://localhost:8080/convert_script' \
  -H 'authority: v3.writerduet.com' \
  -H 'sec-ch-ua: "Google Chrome";v="95", "Chromium";v="95", ";Not A Brand";v="99"' \
  -H 'accept: */*' \
  -H 'sec-ch-ua-mobile: ?0' \
  -H 'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.54 Safari/537.36' \
  -H 'sec-ch-ua-platform: "macOS"' \
  -H 'content-type: multipart/form-data; boundary=----WebKitFormBoundaryUNOCBGPRhGsVNzti' \
  -H 'origin: https://www.writerduet.com' \
  -H 'sec-fetch-site: same-site' \
  -H 'sec-fetch-mode: cors' \
  -H 'sec-fetch-dest: empty' \
  -H 'referer: https://www.writerduet.com/' \
  -H 'accept-language: en-US,en;q=0.9' \
  --data-raw $'------WebKitFormBoundaryUNOCBGPRhGsVNzti\r\nContent-Disposition: form-data; name="ext"\r\n\r\npdf\r\n------WebKitFormBoundaryUNOCBGPRhGsVNzti\r\nContent-Disposition: form-data; name="agree_tou"\r\n\r\ntrue\r\n------WebKitFormBoundaryUNOCBGPRhGsVNzti\r\nContent-Disposition: form-data; name="format"\r\n\r\nfdx\r\n------WebKitFormBoundaryUNOCBGPRhGsVNzti\r\nContent-Disposition: form-data; name="download"\r\n\r\ntrue\r\n------WebKitFormBoundaryUNOCBGPRhGsVNzti\r\nContent-Disposition: form-data; name="script"; filename="file.pdf"\r\nContent-Type: application/octet-stream\r\n\r\n\r\n------WebKitFormBoundaryUNOCBGPRhGsVNzti--\r\n' \
  --compressed

```

3. GCR spins up container(s) using our `Dockerfile`
4. Containerized express app receives the request
5. Controller calls PHP Parser to convert the .pdf file to desired format
6. Controller/app responds with the properly formatted version of the document
7. WD receives document, processes
8. User in WD sees their initially pdf’d document as a WD editable document

![pdf-parser@2x](https://user-images.githubusercontent.com/20424498/139307254-1a89b37b-a23d-4507-84f6-31c9e6068aa7.png)

## Open items and TODO

### Prioritized TODO list

- [x] Make an example pdf with every standard type of format
- [x] Allow for more than one output file at a time and remove after sending
- [x] Establish ability to convert pdf to WD JSON format
  - [ ] ~~Make title page have perfectly accurate lines~~ Add all lines from first page to titlePage JSON
  - [ ] ~~Dual Dialogue~~ Parser should handle, out of scope
  - [x] Random real scripts from WD
- [ ] Set up GCR simulator (if not more than a quick local thing)
- [x] Deploy a test GCR container
- [x] Minimal logging
- [x] Controller validations on POST body (WD client, file size, pdf format)
- [ ] Tests
- [ ] Load test a single instance (could have up to 80? concurrent requests)

#### Random ideas

- test parser like a black box, with expected output files to compare against
- test node stuff
- Set up GCR simulator
- Deploy a test container
- Performance testing a single container
-
- Handle multiple output formats

### Limitations / issues

### Open Questions

---

## Initial Docs from Guy

I finally got together a simplified version of the PHP code which converts PDFs into objects (and optionally outputs as Fountain). It had code to write some other formats too, but none of that is really used anymore, and the .fdx generation is especially uninteresting since it just used some old 3rd party application to convert from .fountain to .fdx (so it’s just playing telephone).

To run it, you’ll need to install pdftohtml first, which I did via

```text
brew install poppler
```

That puts it in /usr/local/bin/pdftohtml though that could be different for you.

Then to execute the php code from the CLI, the following command should work (replacing the path of pdftohtml, or saying just pdftohtml=pdftohtml if that finds it for you)

```text
php analyzer/TestParser.php --fountain pdftohtml=/usr/local/bin/pdftohtml  PATH_TO_PDF.pdf
```

That command generates a Fountain file `output.fountain` . The more interesting part is to just dump the screenplay blocks it’s generating (it has multiple dump points, to show how it’s thinking along the way, but you can modify it to just print at the end with the `print_r($parser->get_objects());` code

```text
php analyzer/TestParser.php -X1707 pdftohtml=pdftohtml PATH_TO_PDF.pdf
```

Take a look at WD’s File > Export option for JSON - I think we’re going to want to use that syntax as the intermediate format the backend app generates directly from the get_objects() result, since it’s the most precise for what we’re doing.

But for testing, starting with Fountain is definitely fine.

And BTW, you’re welcome to deploy to GCR for testing, but that’s a relatively minor detail that we can do at any point. Just getting the Docker ready such that it could be deployed is the main process, it’s totally fine if you’re just testing it locally or want to serve the Docker image from another service if something else is easier for you to use, since Docker should behave the same anywhere it’s deployed

## Useful links

- https://cloud.google.com/run
- https://cloud.google.com/run/docs/quickstarts/build-and-deploy/php
- https://github.com/GoogleCloudPlatform/php-docs-samples/blob/HEAD/run/helloworld/Dockerfile
- https://cloud.google.com/run/docs/how-to
- https://cloud.google.com/run/docs/triggering/https-request
- https://cloud.google.com/run/docs/testing/local#cloud-sdk
- https://github.com/ahmetb/cloud-run-faq
- https://cloud.google.com/run/docs/reference/container-contract?utm_campaign=CDR_ahm_aap-severless_cloud-run-faq_&utm_source=external&utm_medium=web
- https://cloud.google.com/run/docs/quickstarts/build-and-deploy/nodejs
- https://cloud.google.com/run/docs/reference/container-contract#port
- https://nodejs.org/en/docs/guides/nodejs-docker-webapp/
- https://medium.com/@arliber/aws-fargate-from-start-to-finish-for-a-nodejs-app-9a0e5fbf6361
- https://stackoverflow.com/questions/44447821/how-to-create-a-docker-image-for-php-and-node
- https://hub.docker.com/_/php
- https://buddy.works/guides/how-dockerize-node-application
- https://stackoverflow.com/questions/36399848/install-node-in-dockerfile
- https://github.com/milanhlinak/poppler
- https://nodejs.org/en/knowledge/HTTP/servers/how-to-handle-multipart-form-data/
- https://ali-dev.medium.com/how-to-use-promise-with-exec-in-node-js-a39c4d7bbf77
- https://www.adobe.com/documentcloud/acrobat/hub/how-to/how-to-email-large-pdf-files.html#:~:text=Most%20email%20platforms%20limit%20file,make%20them%20more%20email%2Dfriendly.
- https://cloud.google.com/run/docs/tips/general
- https://cloud.google.com/run/docs/testing/local#cloud-sdk
- https://codelabs.developers.google.com/codelabs/cloud-run-deploy#0
