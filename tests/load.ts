const { Cluster } = require("puppeteer-cluster");

// TODO test with bigger example file
(async function () {
  //Create cluster with 10 workers
  const cluster = await Cluster.launch({
    concurrency: Cluster.CONCURRENCY_CONTEXT,
    maxConcurrency: 80,
    monitor: true,
    timeout: 500000,
  });

  // Print errors to console
  cluster.on("taskerror", (err: Error, data) => {
    console.log(`Error crawling ${data}: ${err.message}`);
  });

  // Dumb sleep function to wait for page load
  async function timeout(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  await cluster.task(async ({ page, data: url, worker }) => {
    await page.goto("http://localhost:8080/test");

    const elementHandle = await page.$("input[type=file]");
    await elementHandle.uploadFile("example.pdf");

    const submitButton = "#submit";
    await page.waitForSelector(submitButton);
    await page.click(submitButton);
  });

  for (let i = 1; i <= 200; i++) {
    cluster.queue("http://localhost:8080/test");
  }

  await cluster.idle();
  await cluster.close();
})();
