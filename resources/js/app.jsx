import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './components/Login';
import Register from './components/Register';
import ForgotPassword from './components/PasswordReset';
import Home from './components/Home';
import './echo';

// Mock authentication function (replace with your actual logic)
const isAuthenticated = () => {
    // Check if the user is authenticated (e.g., check for a token in localStorage or cookies)
    return localStorage.getItem('section') !== null;
};

// Protected Route Component
const ProtectedRoute = ({ element }) => {
    return isAuthenticated() ? element : <Navigate to="/" />;
};

function App() {
    return (
        <Router>
            <Routes>
                <Route path="/" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route path="/password-reset" element={<ForgotPassword />} />
                {/* Protect the Home route */}
                <Route path="/Home" element={<ProtectedRoute element={<Home />} />} />
                
            </Routes>
        </Router>
    );
}

const container = document.getElementById('app');
const root = createRoot(container);
root.render(<App />);