import React from 'react';

const Header = ({ title, className }) => {
    return (
        <header className={`header ${className}`}>
            <h1>{title}</h1>
        </header>
    );
};

export default Header;