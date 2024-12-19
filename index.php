<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link href="style.css" rel="stylesheet">

</head>
<body id="bg_1">
    
    <main class="form-signin w-100 m-auto text-center">
        <form action="actions/action_register.php" method="post">
            <h1 class="text-center mt-12 mb-5">Sign Up</h1>

            <div class="form-floating mb-3">
                <input type="text" class="form-control bg-violet" id="floatingInput" placeholder="Name" name="name" required>
                <label for="floatingInput">Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="text" class="form-control bg-violet" id="floatingInput" placeholder="username" name="username" required>
                <label for="floatingInput">Create Username</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control bg-violet" id="floatingPassword" placeholder="Password" name="password" required>
                <label for="floatingPassword">Create Password</label>
            </div>

            <button class="btn btn-light w-50 py-2 mt-3 bg-violet" type="submit">Register</button>
            <p class="mt-5 mb-3 text-body-secondary text-center">Already have an account? <a href="login.php"><span class="text-dark">Log in</span></a></p>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>