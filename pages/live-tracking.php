<?php
/**
 * Live Tracking page for University Bus Booking System
 * Shows real-time location tracking
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../includes/header.php';
?>

<h1 class="page-title">Live Bus Tracking <i class="fas fa-satellite"></i></h1>

<div class="card">
    <div class="live-tracking-header">
        <div class="tracking-info">
            <h3><i class="fas fa-location-dot"></i> Real-time Location</h3>
            <p>View your current location and nearby buses in real-time</p>
        </div>
        <div class="tracking-status">
            <span class="status-badge online"><i class="fas fa-circle"></i> Live</span>
        </div>
    </div>

    <div class="map-container" id="mapContainer">
        <div class="map-placeholder" id="mapPlaceholder">
            <i class="fas fa-map-marked-alt fa-4x"></i>
            <h3>Live Map</h3>
            <p>We need your location permission to start live tracking.</p>
            <button class="btn btn-primary" onclick="initMap()">
                <i class="fas fa-play"></i> Enable & Start Tracking
            </button>
        </div>
        <div class="actual-map" id="actualMap" style="display: none; height: 400px; border-radius: 8px; position: relative;">
        </div>
    </div>

    <div class="tracking-controls">
        <div class="control-group">
            <button class="btn btn-outline" onclick="refreshLocation()">
                <i class="fas fa-sync-alt"></i> Refresh Location
            </button>
            <button class="btn btn-outline" onclick="toggleFullscreen()">
                <i class="fas fa-expand"></i> Fullscreen
            </button>
        </div>
        
        <div class="location-info">
            <h4><i class="fas fa-info-circle"></i> Location Details</h4>
            <div id="locationData">
                <p>Latitude: <span id="latitude">--</span></p>
                <p>Longitude: <span id="longitude">--</span></p>
                <p>Accuracy: <span id="accuracy">--</span> meters</p>
                <p>Last Updated: <span id="lastUpdate">--</span></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-bus"></i> Nearby Buses</h3>
    <div class="nearby-buses" id="nearbyBuses">
        <div class="loading-buses">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Enable tracking to see nearby buses...</p>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
let watchId = null;
let currentLocation = null;
let liveMap = null;
let userMarker = null;
let accuracyCircle = null;
let isTrackingStarted = false;
let busMarkers = {};
let routeLayer = null;
let nearbyBusData = [];

function setLocationDetails(latitude, longitude, accuracy, lastUpdate) {
    document.getElementById('latitude').textContent = latitude;
    document.getElementById('longitude').textContent = longitude;
    document.getElementById('accuracy').textContent = accuracy;
    document.getElementById('lastUpdate').textContent = lastUpdate;
}

function initMap() {
    if (isTrackingStarted) {
        return;
    }
    if (navigator.geolocation) {
        isTrackingStarted = true;
        document.getElementById('mapPlaceholder').style.display = 'none';
        document.getElementById('actualMap').style.display = 'block';

        if (!liveMap) {
            liveMap = L.map('actualMap').setView([23.8103, 90.4125], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(liveMap);
        }

        watchId = navigator.geolocation.watchPosition(
            showPosition,
            handleLocationError,
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );

        setLocationDetails('Locating...', 'Locating...', 'Locating...', 'Waiting for GPS...');
        loadNearbyBuses();
    } else {
        alert('Geolocation is not supported by this browser.');
        setLocationDetails('Unavailable', 'Unavailable', 'Unavailable', 'Unsupported browser');
    }
}

function showPosition(position) {
    currentLocation = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: new Date(position.timestamp)
    };
    
    setLocationDetails(
        currentLocation.latitude.toFixed(6),
        currentLocation.longitude.toFixed(6),
        String(Math.round(currentLocation.accuracy)),
        currentLocation.timestamp.toLocaleTimeString()
    );
    
    updateMapVisualization();
}

function updateMapVisualization() {
    if (!liveMap || !currentLocation) {
        return;
    }

    const latLng = [currentLocation.latitude, currentLocation.longitude];

    if (!userMarker) {
        userMarker = L.marker(latLng).addTo(liveMap).bindPopup('Your live location');
    } else {
        userMarker.setLatLng(latLng);
    }

    if (!accuracyCircle) {
        accuracyCircle = L.circle(latLng, {
            radius: currentLocation.accuracy,
            color: '#2E86DE',
            fillColor: '#2E86DE',
            fillOpacity: 0.15
        }).addTo(liveMap);
    } else {
        accuracyCircle.setLatLng(latLng);
        accuracyCircle.setRadius(currentLocation.accuracy);
    }

    liveMap.setView(latLng, 16);
}

function clearRoute() {
    if (routeLayer && liveMap) {
        liveMap.removeLayer(routeLayer);
        routeLayer = null;
    }
}

async function drawRouteToBus(bus) {
    if (!liveMap || !currentLocation || !bus) {
        return;
    }

    clearRoute();
    const from = [currentLocation.latitude, currentLocation.longitude];
    const to = [bus.lat, bus.lng];

    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${from[1]},${from[0]};${to[1]},${to[0]}?overview=full&geometries=geojson`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Routing service unavailable');
        }
        const data = await response.json();
        if (!data.routes || !data.routes.length) {
            throw new Error('No route found');
        }

        const coords = data.routes[0].geometry.coordinates.map(function(pair) {
            return [pair[1], pair[0]];
        });
        routeLayer = L.polyline(coords, {
            color: '#1e88e5',
            weight: 5,
            opacity: 0.9
        }).addTo(liveMap);
        liveMap.fitBounds(routeLayer.getBounds(), { padding: [30, 30] });
    } catch (e) {
        // Fallback to direct line if routing API is unavailable.
        routeLayer = L.polyline([from, to], {
            color: '#1e88e5',
            weight: 4,
            dashArray: '8, 8',
            opacity: 0.9
        }).addTo(liveMap);
        liveMap.fitBounds(routeLayer.getBounds(), { padding: [30, 30] });
    }
}

async function viewBusOnMap(busId) {
    if (!isTrackingStarted) {
        initMap();
    }
    const bus = nearbyBusData.find(function(item) {
        return item.id === busId;
    });
    if (!bus || !liveMap) {
        return;
    }
    document.getElementById('mapPlaceholder').style.display = 'none';
    document.getElementById('actualMap').style.display = 'block';

    if (busMarkers[bus.id]) {
        busMarkers[bus.id].openPopup();
    }

    await drawRouteToBus(bus);
}

function handleLocationError(error) {
    console.error('Geolocation error:', error);
    let errorMessage = 'Unable to retrieve your location';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorMessage = 'Location access denied. Please enable location services in your browser settings.';
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage = 'Location information is unavailable.';
            break;
        case error.TIMEOUT:
            errorMessage = 'Location request timed out. Please try again.';
            break;
    }
    
    document.getElementById('mapPlaceholder').innerHTML = `
        <i class="fas fa-exclamation-triangle fa-3x" style="color: #e74c3c;"></i>
        <h3>Location Error</h3>
        <p>${errorMessage}</p>
        <button class="btn btn-primary" onclick="initMap()">
            <i class="fas fa-redo"></i> Try Again
        </button>
    `;
    document.getElementById('mapPlaceholder').style.display = 'block';
    document.getElementById('actualMap').style.display = 'none';
    isTrackingStarted = false;
    setLocationDetails('--', '--', '--', errorMessage);
}

function refreshLocation() {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    isTrackingStarted = false;
    initMap();
}

function toggleFullscreen() {
    const mapContainer = document.getElementById('mapContainer');
    if (!document.fullscreenElement) {
        mapContainer.requestFullscreen().catch(err => {
            alert(`Error attempting to enable fullscreen: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}

function loadNearbyBuses() {
    const busesContainer = document.getElementById('nearbyBuses');
    busesContainer.innerHTML = `
        <div class="loading-buses">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Scanning for nearby buses...</p>
        </div>
    `;
    
    setTimeout(() => {
        const baseLat = currentLocation ? currentLocation.latitude : 23.8103;
        const baseLng = currentLocation ? currentLocation.longitude : 90.4125;
        const demoBuses = [
            { id: 'BUS-001', number: 'B23', route: 'Campus - Downtown', distance: '0.8 km', eta: '5 min', lat: baseLat + 0.0042, lng: baseLng + 0.0028 },
            { id: 'BUS-002', number: 'B17', route: 'Campus - Student Village', distance: '1.2 km', eta: '8 min', lat: baseLat - 0.0053, lng: baseLng + 0.0041 },
            { id: 'BUS-003', number: 'B15', route: 'Campus - Faculty Housing', distance: '2.1 km', eta: '12 min', lat: baseLat + 0.0065, lng: baseLng - 0.0054 }
        ];
        nearbyBusData = demoBuses;
        
        busesContainer.innerHTML = '';

        // Reset previous bus markers before drawing latest nearby bus positions.
        Object.keys(busMarkers).forEach(function(key) {
            if (liveMap && busMarkers[key]) {
                liveMap.removeLayer(busMarkers[key]);
            }
        });
        busMarkers = {};
        
        demoBuses.forEach(bus => {
            if (liveMap) {
                busMarkers[bus.id] = L.marker([bus.lat, bus.lng]).addTo(liveMap)
                    .bindPopup(`Bus ${bus.number}<br>${bus.route}<br>ETA: ${bus.eta}`);
            }

            const busElement = document.createElement('div');
            busElement.className = 'bus-item';
            busElement.innerHTML = `
                <div class="bus-info">
                    <h4>Bus ${bus.number} <span class="bus-distance">${bus.distance}</span></h4>
                    <p>${bus.route}</p>
                    <div class="bus-eta">
                        <i class="fas fa-clock"></i> ETA: ${bus.eta}
                    </div>
                </div>
                <div class="bus-actions">
                    <button class="btn btn-sm btn-outline" data-bus-id="${bus.id}">
                        <i class="fas fa-eye"></i> View
                    </button>
                </div>
            `;
            const viewButton = busElement.querySelector('[data-bus-id]');
            if (viewButton) {
                viewButton.addEventListener('click', function() {
                    viewBusOnMap(bus.id);
                });
            }
            busesContainer.appendChild(busElement);
        });
    }, 2000);
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto') === 'true') {
        initMap();
    }
});

window.addEventListener('beforeunload', function() {
    if (watchId !== null && navigator.geolocation) {
        navigator.geolocation.clearWatch(watchId);
    }
});

document.addEventListener('fullscreenchange', function() {
    if (liveMap) {
        setTimeout(function() {
            liveMap.invalidateSize();
        }, 120);
    }
});
</script>

<style>
.live-tracking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
.tracking-status .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: bold; }
.status-badge.online { background-color: var(--secondary); color: white; animation: pulse-live 2s infinite; }
.status-badge i { font-size: 0.6rem; margin-right: 5px; }
.map-container { margin: 20px 0; border-radius: 8px; overflow: hidden; }
.actual-map { min-height: 400px; }
.map-placeholder { text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; }
.map-placeholder i { margin-bottom: 15px; }
.map-container:fullscreen {
    width: 100vw;
    height: 100vh;
    max-width: 100vw;
    max-height: 100vh;
    margin: 0;
    border-radius: 0;
    background: #111;
}
.map-container:fullscreen #actualMap,
.map-container:fullscreen .map-placeholder {
    width: 100%;
    height: 100%;
    min-height: 100%;
    border-radius: 0;
}
.tracking-controls { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 20px; gap: 30px; }
.control-group { display: flex; gap: 10px; flex-wrap: wrap; }
.location-info { flex: 1; background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary); }
.location-info h4 { margin-bottom: 10px; color: var(--dark); display: flex; align-items: center; gap: 8px; }
.nearby-buses { margin-top: 15px; }
.loading-buses { text-align: center; padding: 30px; color: var(--grey); }
.bus-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; margin: 10px 0; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--primary); transition: transform 0.2s ease; }
.bus-item:hover { transform: translateX(5px); }
.bus-info h4 { margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
.bus-distance { background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
.bus-eta { color: var(--secondary); font-weight: bold; margin-top: 5px; display: flex; align-items: center; gap: 5px; }
.no-buses { text-align: center; padding: 20px; color: var(--grey); font-style: italic; }
.btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); transition: all 0.3s ease; }
.btn-outline:hover { background: var(--primary); color: white; transform: translateY(-2px); }

@keyframes pulse-live {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@media (max-width: 768px) {
    .live-tracking-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .tracking-controls { flex-direction: column; gap: 20px; }
    .control-group { justify-content: center; }
    .bus-item { flex-direction: column; align-items: flex-start; gap: 10px; }
    .bus-actions { align-self: stretch; text-align: center; }
    .location-info { text-align: center; }
}
</style>

<?php
require_once '../includes/footer.php';
?>