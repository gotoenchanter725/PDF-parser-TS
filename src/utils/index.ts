import * as path from "path";
import { exec } from "child_process";
import * as multer from "multer";

/**
 * Executes a shell command and return it as a Promise.
 * https://ali-dev.medium.com/how-to-use-promise-with-exec-in-node-js-a39c4d7bbf77
 * @param cmd {string}
 * @return {Promise<string>}
 */
exports.execAsync = async function (cmd: string): Promise<string> {
  return new Promise((resolve, reject) => {
    exec(cmd, (error, stdout, stderr) => {
      if (error) {
        reject(error);
      }

      resolve(stdout ? stdout : stderr);
    });
  });
};

/**
 * File handling in express routes
 */
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, "uploads/");
  },
  filename: function (req, file, cb) {
    const randomNumber = Math.floor(Math.random() * 1000000000);
    const uniqueFilename = `${Date.now()}-${randomNumber}`;

    cb(null, `${uniqueFilename}${path.extname(file.originalname)}`);
  },
});

exports.upload = multer({ storage });

exports.logger = require("./logger");
