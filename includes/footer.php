<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <div class="footer-logo">
                <h3>Pirate's Blood</h3>
                <p class="established">Established 2025</p>
            </div>
        </div>
        
        <div class="footer-section">
            <h4>Contact Information</h4>
            <div class="contact-info">
                <p><i class="fas fa-phone"></i> Emergency: (555) 123-BLOOD</p>
                <p><i class="fas fa-phone-alt"></i> General: (02) 8234-5678</p>
                <p><i class="fas fa-envelope"></i> info@piratesblood.ph</p>
                <p><i class="fas fa-globe"></i> www.piratesblood.ph</p>
            </div>
        </div>
        
        <div class="footer-section">
            <h4>Our Locations</h4>
            <div class="locations">
                <p><i class="fas fa-map-marker-alt"></i> Metro Manila Hub<br>
                   <small>123 Aurora Blvd, Quezon City</small></p>
                <p><i class="fas fa-map-marker-alt"></i> Laguna Branch<br>
                   <small>87 Mabini St, Calamba</small></p>
                <p><i class="fas fa-map-marker-alt"></i> Davao Center<br>
                   <small>210 Bonifacio St, Davao City</small></p>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; 2025 Pirate's Blood. All rights reserved. | Saving lives, one drop at a time.</p>
    </div>
</footer>

<style>
.footer {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-top: 2px solid #f0f0f0;
    box-shadow: 0 -2px 15px rgba(220, 220, 220, 0.1);
    color: #495057;
    margin-top: auto;
    padding: 50px 20px 30px;
    font-family: 'Poppins', sans-serif;
    margin-left: 70px;
    transition: margin-left 0.3s ease;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 40px;
    margin-bottom: 30px;
}

.footer-section {
    padding: 20px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
    transition: all 0.3s ease;
}

.footer-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
}

.footer-section h3 {
    color: #dc3545;
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 10px;
    font-family: 'Poppins', sans-serif;
}

.footer-section h4 {
    color: #495057;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dc3545;
    padding-bottom: 10px;
    display: inline-block;
}

.established {
    color: #6c757d;
    font-size: 14px;
    font-style: italic;
    margin: 0;
    font-weight: 500;
}

.contact-info p,
.locations p {
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    line-height: 1.6;
    color: #495057;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 8px;
    border-radius: 8px;
}

.contact-info p:hover,
.locations p:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    transform: translateX(5px);
}

.contact-info i,
.locations i {
    color: #dc3545;
    font-size: 16px;
    width: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}

.locations small {
    color: #6c757d;
    font-size: 13px;
    margin-left: 30px;
    display: block;
    margin-top: 3px;
    font-weight: 400;
}

.footer-bottom {
    border-top: 2px solid #f0f0f0;
    padding-top: 25px;
    text-align: center;
    color: #6c757d;
    font-size: 14px;
    font-weight: 500;
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
    margin: 0 -20px -30px -20px;
    padding-left: 20px;
    padding-right: 20px;
    padding-bottom: 30px;
    border-radius: 0 0 15px 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .footer {
        margin-left: 0;
        padding: 30px 15px 20px;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .footer-section {
        text-align: center;
        padding: 25px 20px;
    }
    
    .contact-info p,
    .locations p {
        justify-content: center;
        text-align: left;
    }
    
    .locations small {
        margin-left: 0;
        text-align: center;
        margin-top: 5px;
    }
    
    .footer-bottom {
        margin: 20px -15px -20px -15px;
        padding: 20px 15px;
    }
}

@media (max-width: 480px) {
    .footer-section h3 {
        font-size: 28px;
    }
    
    .footer-section h4 {
        font-size: 16px;
    }
    
    .footer-section {
        padding: 20px 15px;
    }
    
    .contact-info p,
    .locations p {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 8px;
    }
    
    .contact-info i,
    .locations i {
        margin-top: 0;
    }
    
    .locations small {
        margin-left: 0;
    }
}

/* Sidebar hover effect on footer */
@media (min-width: 769px) {
    .sidebar:hover ~ main .footer,
    .sidebar:hover ~ .footer {
        margin-left: 280px;
    }
}
</style>