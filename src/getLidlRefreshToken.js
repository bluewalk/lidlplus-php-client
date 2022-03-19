/**
 * Get refresh_token for Lidl Plus API
 *
 * By: Bastiaan Steinmeier, https://github.com/basst85
 * Modified by: https://github.com/UNICodehORN
 * Modified again by: https://github.com/Sharknoon
 *
 */

const { Issuer, generators } = require('openid-client');
const puppeteer = require('puppeteer');
const devices = puppeteer.devices;
const iPhone = devices['iPhone 5'];
const urlparse = require('url');
const req = require('request');
const readline = require('readline').createInterface({
    input: process.stdin,
    output: process.stdout
});

function question(query) {
    return new Promise(resolve => {
        readline.question(query, resolve);
    })
}

Issuer.discover('https://accounts.lidl.com')
    .then(function (openidIssuer) {
        const nonce = generators.nonce();
        const code_verifier = generators.codeVerifier();
        const code_challenge = generators.codeChallenge(code_verifier);

        const client = new openidIssuer.Client({
            client_id: 'LidlPlusNativeClient',
            redirect_uris: ['com.lidlplus.app://callback'],
            response_types: ['code']
        });

        const loginurl = client.authorizationUrl({
            scope: 'openid profile offline_access lpprofile lpapis',
            code_challenge,
            code_challenge_method: 'S256'
        });

        (async () => {
            const login_country = await question('Please enter your country code (DE, NL, ...): ');
            const login_language = await question('Please enter your language code (DE-DE, NL-NL, ...): ');

            console.log('In case your refresh_token cannot be retrieved open this url once in your browser and accept the terms and conditions of the given country:\n');
            console.log(loginurl + '&Country=' + login_country + '&language=' + login_language);

            const browser = await puppeteer.launch();
            const page = await browser.newPage();
            await page.emulate(iPhone);
            await page.goto(loginurl + '&Country=' + login_country + '&language=' + login_language);
            await new Promise(r => setTimeout(r, 1000));
            await page.click('#button_welcome_login', { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 3000));
            await page.click('[name="EmailOrPhone"]', { waitUntil: 'networkidle0' });
            const login_email = await question('Please enter your LIDL Plus e-mail: ');
            await page.keyboard.type(login_email, { waitUntil: 'networkidle0' });
            await page.click('#button_btn_submit_email', { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 3000));
            await page.click('[name="Password"]', { waitUntil: 'networkidle0' });
            const login_password = await question('Please enter your LIDL Plus password: ')
            await page.keyboard.type(login_password, { waitUntil: 'networkidle0' });
            await page.click('#button_submit', { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 3000));
            await page.click('button', { waitUntil: 'networkidle0' });
            await new Promise(r => setTimeout(r, 3000));
            await page.click('#field_VerificationCode', { waitUntil: 'networkidle0' });
            const code = await question('Please check your e-mails and enter the received code here: ');
            await page.keyboard.type(code, { waitUntil: 'networkidle0' });

            page.on('request', request => {
                if (request.isNavigationRequest()) {
                    if (request._url.includes('com.lidlplus.app://callback')) {
                        var url_parts = urlparse.parse(request._url, true);
                        console.log('Query:\n', url_parts.query);
                        var query = url_parts.query;
                        console.log('auth-code:\n', query.code);

                        var tokenurl = 'https://accounts.lidl.com/connect/token';
                        var headers = {
                            'Authorization': 'Basic TGlkbFBsdXNOYXRpdmVDbGllbnQ6c2VjcmV0',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        };
                        var form = {
                            grant_type: 'authorization_code',
                            code: query.code,
                            redirect_uri: 'com.lidlplus.app://callback',
                            code_verifier: code_verifier
                        };

                        req.post({ url: tokenurl, form: form, headers: headers, json: true }, function (e, r, body) {
                            console.log('BODY:\n', body, '\n');
                            console.log('Access token:\n', body.access_token, '\n');
                            console.log('Refresh token:\n', body.refresh_token);
                        });
                    } else {
                        console.log('undefined document!!!');
                    }
                } else {
                    console.log('undefined request...   ', request._url);
                }
                request.continue().catch((err) => {
                });
            });

            console.log("submit...");
            await page.click('button', { waitUntil: 'networkidle0' });

            await new Promise(r => setTimeout(r, 15000));
            await browser.close();
        })();

    });
