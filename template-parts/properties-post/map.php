<?php
/**
 * Template part for displaying the property compound map.
 *
 * It fetches the polygon coordinates from the 'compound_polygon_points' post meta
 * and uses Leaflet.js to render a map with the polygon overlay.
 *
 * @package CobTheme
 */

$post_id = get_the_ID();
// Fetch polygon data from the custom field
$polygon_data = get_post_meta($post_id, 'compound_polygon_points', true);

// Do not render anything if the data is empty.
if (empty($polygon_data)) {
    return;
}
?>
<style>
.leaflet-control-attribution.leaflet-control {
    display: none !important;
}
</style>
<div class="container" style="margin-top: 30px;">
    <h3 style="margin-bottom: 15px;"><?php _e('Compound Borders Map', 'cob_theme'); ?></h3>
    <div id="property-compound-map" style="height: 450px; width: 100%; border-radius: 8px; border: 1px solid #ddd;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Ensure the Leaflet library is available
    if (typeof L === 'undefined') {
        console.error('Leaflet library is not loaded.');
        return;
    }

    // Get the polygon data string passed from PHP
    const polygonDataString = <?php echo json_encode($polygon_data); ?>;

    // Initialize the map
    const map = L.map('property-compound-map');

    // Add the base tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    try {
        // **FIX:** Directly parse the string from the database. This is more robust.
        // It correctly handles a string that is a valid JSON array representation.
        const rawCoords = JSON.parse(polygonDataString);

        // Validate that the parsed data is an array.
        if (!Array.isArray(rawCoords)) {
            throw new Error("Parsed data is not a valid array.");
        }

        // Leaflet requires [latitude, longitude], but the data is [longitude, latitude]. Swap them.
        // Also, filter out any invalid coordinate pairs.
        const leafletCoords = rawCoords.map(coord => {
            if (Array.isArray(coord) && coord.length === 2 && !isNaN(coord[0]) && !isNaN(coord[1])) {
                return [coord[1], coord[0]]; // Swap [lon, lat] to [lat, lon]
            }
            return null;
        }).filter(coord => coord !== null);

        // Ensure there are valid coordinates to draw.
        if (leafletCoords.length < 3) {
             throw new Error("Not enough valid coordinates to draw a polygon.");
        }

        // Create the polygon and add it to the map
        const polygon = L.polygon(leafletCoords, { 
            color: 'var(--mainColor)',      // Border color
            fillColor: 'var(--mainColor)',  // Fill color
            fillOpacity: 0.4       // Fill opacity
        }).addTo(map);

        // Add a tooltip
        polygon.bindTooltip("<?php echo esc_js(get_the_title() . ' Borders'); ?>");

        // Automatically zoom and center the map to fit the polygon
        map.fitBounds(polygon.getBounds(), { padding: [20, 20] });

    } catch (e) {
        console.error("Error processing polygon coordinates:", e);
        // Also log the original string for easier debugging
        console.error("Original data string:", polygonDataString);
        // Hide the map container if an error occurs
        document.getElementById('property-compound-map').style.display = 'none';
    }
});
</script>
