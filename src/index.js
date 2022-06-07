"use strict";

const express = require("express");
const path = require("path");
const cors = require("cors");
const { upload, logger } = require("./utils");
const httpLoggingMiddleware = require("./http.middleware");

const app = express();

app.use(cors()); // Is this needed? Test without in GCR simulator with request from different port

app.use(express.urlencoded({ extended: false }));
app.use(express.json());
app.use(httpLoggingMiddleware);

/* Routes */
app.get("/", (req, res) => {
  res.status(200).send("OK");
});

app.get("/test", require("./test.controller"));

app.post(
  "/convert_script",
  upload.single("script"),
  require("./convert_script.controller")
);

/* Run the app */
const PORT = process.env.PORT || 8080;
const HOST = "0.0.0.0";

app.listen(PORT, HOST);

global.PATH_TO_PDFTOHTML = process.env.PATH_TO_PDFTOHTML
  ? process.env.PATH_TO_PDFTOHTML
  : process.env.IS_CONTAINER
  ? "pdftohtml"
  : "/usr/local/bin/pdftohtml";

logger.info("--------");
logger.info(`Running on http://${HOST}:${PORT}`);
logger.info(`NODE_ENV? ${process.env.NODE_ENV}`);
logger.info(`IS_CONTAINER? ${process.env.IS_CONTAINER || false}`);
logger.info(`IS_LOCAL? ${process.env.IS_LOCAL || false}`);
logger.info("--------");
