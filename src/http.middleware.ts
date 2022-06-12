const { logger } = require("./utils");
import {Request, Response, NextFunction} from "express";

module.exports = (req: Request, res: Response, next: NextFunction) => {
  const { method, url } = req;

  logger.info(`${method} ${url}`);

  return next();
};
