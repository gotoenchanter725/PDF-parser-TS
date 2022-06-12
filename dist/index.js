"use strict";
exports.__esModule = true;
var express = require("express");
var cors = require("cors");
var _a = require("./utils"), upload = _a.upload, logger = _a.logger;
var httpLoggingMiddleware = require("./http.middleware");
require("dotenv").config();
var app = express();
// app.use(cors()); // Is this needed? Test without in GCR simulator with request from different port
app.use(express.urlencoded({ extended: false }));
app.use(express.json());
app.use(httpLoggingMiddleware);
/* Routes */
app.get("/", function (req, res) {
    res.status(200).send("OK");
});
app.get("/test", require("./test.controller"));
// app.post(
//   "/convert_script",
//   upload.single("script"),
//   require("./convert_script.controller")
// );
/* Run the app */
var PORT = process.env.PORT || 8080;
var HOST = "127.0.0.1";
app.listen(PORT, HOST);
// declare var PATH_TO_PDFTOHTML: string;
globalThis.PATH_TO_PDFTOHTML = "pdftohtml";
console.log(globalThis.PATH_TO_PDFTOHTML);
logger.info("--------");
logger.info("Running on http://".concat(HOST, ":").concat(PORT));
logger.info("NODE_ENV? ".concat(process.env.NODE_ENV));
logger.info("IS_CONTAINER? ".concat(process.env.IS_CONTAINER || false));
logger.info("IS_LOCAL? ".concat(process.env.IS_LOCAL || false));
logger.info("--------");
