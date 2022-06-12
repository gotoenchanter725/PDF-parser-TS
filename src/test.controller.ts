import * as path from "path";
import {Request, Response, NextFunction} from "express";

module.exports = async function (req: Request, res: Response, next: NextFunction) {
  // make things easy to test in local environment
  if (process.env.IS_LOCAL) {
    return res.sendFile(path.join(__dirname, "../test.html"));
  }

  return next();
};
