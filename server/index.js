// This backend server is an API-only Express server. It does not return pages, but instead serves JSON data to the React frontend.
// It also handles LTI authentication, security middleware, database interactions and other server-side logic.

const express = require("express");
const app = express();

app.get("/", function(req, res) {
    return res.send("Hello World... UDOIT FOREVER!!!");
});

app.listen(3001, function(){
    console.log('Listening on port 3001');
});