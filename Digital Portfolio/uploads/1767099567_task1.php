
<?

$digit = 10; // Example input, you can change this or take from user input

if ($digit < 1 || $digit > 10) 
   {echo "Invalid figure";}

elseif ($digit >= 1 && $digit <= 3)
    { echo "Very bad";} 

elseif ($digit == 4 || $digit == 5)
    { echo "Insufficient";}

 elseif ($digit == 6 || $digit == 7) 
   { echo "Sufficient";}

 elseif ($digit == 8)
     {echo "Good";} 

elseif ($digit == 9) 
    {echo "Very good";}

 elseif ($digit == 10) 
    {echo "Excellent";}




?>