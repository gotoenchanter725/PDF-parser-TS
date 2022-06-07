const { logger } = require("./utils");

module.exports = (req, res, next) => {
  const { method, url } = req;

  logger.info(`${method} ${url}`);

  return next();
};
