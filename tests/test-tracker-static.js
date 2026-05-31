const fs = require('fs');
const path = require('path');
const vm = require('vm');

const scriptPath = path.join(__dirname, '..', 'open-tracker', 'assets', 'js', 'ot-tracker.js');
const source = fs.readFileSync(scriptPath, 'utf8');
const requests = [];

function FakeXHR() {
	this.headers = {};
	this.readyState = 0;
	this.status = 0;
	this.responseText = '';
}

FakeXHR.prototype.open = function (method, url) {
	this.method = method;
	this.url = url;
};

FakeXHR.prototype.setRequestHeader = function (name, value) {
	this.headers[name] = value;
};

FakeXHR.prototype.send = function (body) {
	this.body = body;
	requests.push(this);
	this.readyState = 4;
	this.status = 201;
	this.responseText = '{"visit_id":42}';
	if (this.onreadystatechange) {
		this.onreadystatechange();
	}
};

const context = {
	XMLHttpRequest: FakeXHR,
	setInterval: function () {
		return 1;
	},
	clearInterval: function () {},
	window: {
		location: {
			href: 'https://playground.lieuwejongsma.nl/demo/index.html?x=1',
		},
		addEventListener: function () {},
	},
	document: {
		currentScript: {
			getAttribute: function (name) {
				if (name === 'data-ot-rest-url') {
					return 'https://www.lieuwejongsma.nl/wp-json/open-tracker/v1/';
				}

				return null;
			},
		},
		referrer: 'https://www.lieuwejongsma.nl/projects/',
		hidden: false,
		readyState: 'complete',
		addEventListener: function () {},
	},
};

vm.runInNewContext(source, context);

if (requests.length !== 1) {
	throw new Error(`Expected one initial hit request, got ${requests.length}.`);
}

const hit = requests[0];
const body = JSON.parse(hit.body);

if (hit.method !== 'POST') {
	throw new Error(`Expected POST, got ${hit.method}.`);
}

if (hit.url !== 'https://www.lieuwejongsma.nl/wp-json/open-tracker/v1/hit') {
	throw new Error(`Unexpected hit URL: ${hit.url}`);
}

if (hit.headers['X-WP-Nonce']) {
	throw new Error('Static tracker requests must not send X-WP-Nonce.');
}

if (body.page_url !== 'https://playground.lieuwejongsma.nl/demo/index.html?x=1') {
	throw new Error(`Unexpected page_url: ${body.page_url}`);
}

if (body.referrer !== 'https://www.lieuwejongsma.nl/projects/') {
	throw new Error(`Unexpected referrer: ${body.referrer}`);
}

console.log('Static tracker tests passed.');
