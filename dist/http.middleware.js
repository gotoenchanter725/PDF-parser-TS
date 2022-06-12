"use strict";
exports.__esModule = true;
var logger = require("./utils").logger;
module.exports = function (req, res, next) {
    var method = req.method, url = req.url;
    logger.info("".concat(method, " ").concat(url));
    return next();
};
