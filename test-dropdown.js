const fs = require('fs');
const path = require('path');
const bootstrap = fs.readFileSync(path.join(__dirname, 'agent-tools/bootstrap-test.js'), 'utf8');

// Simulate bootstrap merge - extract function g
const s = fs.readFileSync('C:/Users/hp/.cursor/projects/c-wamp64-www-Hopitaux/agent-tools/b6ac1692-0019-4daf-b1da-4dd395005c1d.txt', 'utf8');

// Test getOrCreateInstance with popperConfig false
const { JSDOM } = require('jsdom');
