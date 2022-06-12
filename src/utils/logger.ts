// https://github.com/winstonjs/winston/blob/master/examples/quick-start.js
import { createLogger, format, transports, config } from "winston";

const fileLogFormat = format.combine(
  format.timestamp({
    format: "YYYY-MM-DD HH:mm:ss",
  }),
  format.errors({ stack: true }),
  format.splat(),
  format.metadata({ fillExcept: ["message", "level", "timestamp", "label"] }),
  format.json()
);

const consoleFormat = format.printf(({ level, message, label, timestamp }) => {
  return `\x1b[2m[${timestamp}\x1b[0m:${level}] ${message}`;
});

const logger = createLogger({
  transports: [
    new transports.Console({
      format: format.combine(
        format.timestamp({
          format: "YYYY-MM-DD HH:mm:ss",
        }),
        format.colorize(),
        consoleFormat
      ),
    }),
    new transports.File({
      filename: "logs/error.log",
      level: "error",
      format: fileLogFormat,
    }),
    // new transports.File({
    //   filename: "logs/combined.log",
    //   format: fileLogFormat,
    // }),
  ],
});

module.exports = logger;
