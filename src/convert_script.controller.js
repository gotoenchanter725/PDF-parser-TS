const path = require("path");
const fsPromises = require("fs").promises;
const fs = require("fs");
const randomString = require("randomstring");
const { execAsync, logger } = require("./utils");
const axios = require("axios");
var FormData = require("form-data");

const formats = {
  fdx: {
    flag: "--fountain",
    extension: ".fountain",
  },
  json: {
    flag: "--json",
    extension: ".json",
  },
};

// generate random file name
function generateFileName() {
  return randomString.generate(8);
}

async function convertFileToPDF(file) {
  var formData = new FormData();

  formData.append("format", "pdf");
  formData.append("file", fs.createReadStream(file.path));

  return new Promise((resolve) => {
    axios
      .post(
        "https://office-to-pdf-image-x3dodtiy2q-uc.a.run.app/convert",
        formData,
        {
          responseType: "arraybuffer",
          headers: {
            "Content-Type": "multipart/form-data",
            Accept: "application/pdf",
          },
        }
      )
      .then(async (res) => {
        let newPDFFilePath = "./uploads/" + generateFileName() + ".pdf";

        await fs.createWriteStream(newPDFFilePath).write(res.data, async () => {
          resolve(newPDFFilePath);
        });
      })
      .catch((err) => {
        console.log(err);
        resolve("ERROR");
      });
  });
}

module.exports = async function (req, res, next) {
  const { file, body, rawBody, files } = req;
  const { format, needsConvert } = body;

  function logMessage(message) {
    return logger.info(`[${file.filename.replace(".pdf", "")}]: ${message}`);
  }

  const fileSizeMB = convertBytesToMb(file.size);

  logMessage(`Attempting to convert ${fileSizeMB} MB file`);

  const startTime = Date.now();

  if (!(file && file.filename)) {
    return res.status(400).send("File is required");
  }

  if (!formats[format]) {
    return res.status(400).send("Invalid format provided");
  }

  if (false && file.mimetype !== "application/pdf") {
    return res.status(415).send("File must be pdf");
  }

  // 20MB seems to be a common limit on upload
  if (fileSizeMB > 20) {
    return res.status(413).send("File size cannot exceed 20MB");
  }

  const outputFilename = `outputs/${
    generateFileName() + formats[format]["extension"]
  }`;
  let originalFilePath = file.path;

  if (needsConvert) {
    file.path = await convertFileToPDF(file);

    if (file.path == "ERROR") {
      fsPromises
        .unlink(originalFilePath)
        .then(() => {
          logMessage("Original file path (for non PDFs) deleted successfully");
          return res
            .status(415)
            .send("Conversion of this file type to PDF failed.");
        })
        .catch((err) => {
          logMessage(
            "Error removing original file path (for non PDFs)",
            "error"
          );
          return res
            .status(415)
            .send(
              "Conversion of this file type to PDF failed (And it failed at deleting the file from the server)."
            );
        });
    }
  }

  try {
    // convert the pdf file to desired output

    await execAsync(
      `npm run parse -- ${formats[format]["flag"]} outputFilename=${outputFilename} pdftohtml=${PATH_TO_PDFTOHTML} ${file.path}`
    );

    res.sendFile(path.join(__dirname, `../${outputFilename}`));

    // response sent, do some clean up
    const data = await fsPromises.stat(
      path.join(__dirname, `../${outputFilename}`)
    );

    logMessage(
      `Successfully output a ${data.size} byte file converted in ${
        Date.now() - startTime
      } ms`
    );
  } catch (err) {
    logMessage(
      `Error parsing ${fileSizeMB} MB file after ${Date.now() - startTime} ms`,
      { error: JSON.stringify(err.stack) },
      "error"
    );

    next(err);
  } finally {
    // delete the input and output files - need to wait to avoid several potential issues
    setTimeout(() => {
      fsPromises
        .unlink(file.path)
        .then(() => logMessage("Original pdf deleted successfully"))
        .catch((err) =>
          logMessage("Error removing uploaded pdf file", "error")
        );
      fsPromises
        .unlink(outputFilename)
        .then(() => logMessage("Output file deleted successfully"))
        .catch((err) => logMessage("Error removing output file", "error"));

      if (needsConvert) {
        fsPromises
          .unlink(originalFilePath)
          .then(() =>
            logMessage("Original file path (for non PDFs) deleted successfully")
          )
          .catch((err) =>
            logMessage(
              "Error removing original file path (for non PDFs)",
              "error"
            )
          );
      }
    }, 0);
  }
};

function convertBytesToMb(bytes) {
  return (Number(bytes) / 1000000).toFixed(3);
}
