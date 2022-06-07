const path = require("path");

module.exports = async function (req, res, next) {
  // make things easy to test in local environment
  if (process.env.IS_LOCAL) {
    return res.sendFile(path.join(__dirname, "../test.html"));
  }

  return next();
};
