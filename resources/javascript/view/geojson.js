"use strict";

import "leaflet/dist/leaflet.css";

import L from "leaflet";

export default function () {
  const map = L.map("map").setView([0, 0], 0);

  L.control.scale().addTo(map);

  L.tileLayer("https://tile.openstreetmap.be/osmbe/{z}/{x}/{y}.png", {
    attribution:
      'Map data &copy; <a href="https://www.openstreetmap.org/" target="_blank">OpenStreetMap</a> contributors, ' +
      'Tiles courtesy of <a href="https://geo6.be/" target="_blank">GEO-6</a>.',
  }).addTo(map);

  const group = L.layerGroup().addTo(map);

  $("#modal-view-geojson").on("shown.bs.modal", () => {
    map.invalidateSize();
  });

  $("#modal-view-geojson").on("show.bs.modal", (event) => {
    const link = event.relatedTarget;
    const href = $(link).attr("href");

    group.clearLayers();

    fetch(href)
      .then((response) => {
        return response.json();
      })
      .then((json) => {
        const options = {
          pointToLayer: (feature, latlng) => {
            return L.circleMarker(latlng, {
              radius: 5,
            });
          },
          style: {
            interactive: false,
          },
        };
        if (typeof json.legend !== "undefined") {
          options.style = (feature) => {
            const color = feature.properties.color || "#fff";

            return {
              interactive: false,
              fillColor: color,
              color: "#000",
              weight: 1,
              opacity: 1,
              fillOpacity: 0.8,
            };
          };

          const ul = document.createElement("ul");

          $(ul).addClass("list-unstyled mb-0");

          json.legend.map((item, index) => {
            const li = document.createElement("li");
            const { text, color } = item;

            $(li)
              .append(
                `<i class="fas fa-circle" style="color: ${color}"></i> ${text}`
              )
              .appendTo(ul);
          });

          $("#map-legend").html(ul).show();
        } else {
          $("#map-legend").empty().hide();
        }

        const geojson = L.geoJSON(json, options).addTo(group);

        if (geojson.getBounds().isValid() === true) {
          map.fitBounds(geojson.getBounds(), {
            padding: [15, 15],
          });
        }
      });
  });
}
