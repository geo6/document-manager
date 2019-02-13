'use strict';

/**
 *
 * @param {string} url
 * @param {string} method
 * @param {object} data
 * @param {function} callback
 */
export default function (url, method, data, callback) {
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(response => {
        if (response.ok !== true) {
            throw new Error(response.statusText);
        }

        return response.json();
    }).then(json => {
        callback(json);
    });
}
