'use strict';

/**
 *
 * @param {string} url
 * @param {string} method
 * @param {object} data
 * @param {function} callback
 */
export default function (url, method, data, callback) {
    let init = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (method === 'GET' || method === 'HEAD') {
        url += '?'+$.param(data);
    } else {
        init.body = JSON.stringify(data);
    }

    fetch(url, init).then(response => {
        if (response.ok !== true) {
            throw new Error(response.statusText);
        }

        return response.json();
    }).then(json => {
        callback(json);
    });
}
